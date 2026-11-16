<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\Hold;
use App\Models\Payment;
use App\Services\Booking\BookingFinalizer;
use App\Services\Inventory\HoldService;
use App\Support\PaymentLedger;
use App\Support\PaymentMode;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private PaymentProviderInterface $provider,
        private BookingFinalizer $finalizer,
        private HoldService $holds,
    ) {
    }

    /** Daftar metode per kategori beserta biaya yang dihitung SERVER untuk total tertentu. */
    public function catalog(int $baseTotal): array
    {
        $out = [];

        foreach (config('payment.categories') as $key => $category) {
            $methods = [];
            foreach (config('payment.methods') as $code => $m) {
                if ($m['category'] === $key) {
                    $methods[$code] = ['label' => $m['label'], 'fee' => PaymentMode::fee($code, $baseTotal)];
                }
            }
            $out[$key] = $category + ['methods' => $methods];
        }

        return $out;
    }

    /**
     * Buat tagihan Pending untuk sebuah booking. Panggil DI DALAM transaksi.
     */
    public function makePayment(Booking $booking, Hold $hold, string $methodCode, string $idempotencyKey, Carbon $expiresAt): Payment
    {
        $method = PaymentMode::method($methodCode);
        if (! $method) {
            throw new PaymentException('Metode pembayaran tidak valid.');
        }

        $fee = PaymentMode::fee($methodCode, (int) $hold->total_amount);
        $total = (int) $hold->total_amount + $fee;

        $booking->update(['fee_amount' => $fee, 'total_amount' => $total]);

        $payment = Payment::create([
            'payment_reference' => $this->unique('payment_reference', 'PAY-', 12),
            'invoice_number' => $this->unique('invoice_number', 'INV-'.now()->format('Ymd').'-', 6),
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'method_code' => $methodCode,
            'method_category' => $method['category'],
            'provider' => $this->provider->name(),
            'currency' => 'IDR',
            'amount' => $total,
            'fee_amount' => $fee,
            'status' => Payment::PENDING,
            'is_simulated' => $this->provider->isSimulation(),
            'idempotency_key' => $idempotencyKey,
            'expires_at' => $expiresAt,
        ]);

        // Bila provider menolak, pengecualian dilempar dan seluruh transaksi dibatalkan.
        $charge = $this->provider->createCharge($payment, $method);

        $payment->update([
            'provider_reference' => $charge['provider_reference'] ?? null,
            'payment_code' => $charge['payment_code'] ?? null,
            'qr_payload' => $charge['qr_payload'] ?? null,
            'instructions' => $charge['instructions'] ?? null,
        ]);

        PaymentLedger::record($payment, 'payment_created', null, Payment::PENDING, 'system', [
            'method' => $methodCode, 'amount' => $total, 'fee' => $fee,
        ]);

        return $payment;
    }

    /**
     * Ganti metode (dari Pending) atau coba lagi (dari Failed).
     * Hanya bila booking Pending dan hold masih aktif.
     */
    public function replaceMethod(Payment $old, $user, string $methodCode): Payment
    {
        return DB::transaction(function () use ($old, $user, $methodCode) {
            $bookingId = Payment::whereKey($old->id)->value('booking_id');
            $holdId = Booking::whereKey($bookingId)->value('hold_id');

            // Urutan kunci: hold, booking, tagihan.
            $hold = $holdId ? Hold::whereKey($holdId)->lockForUpdate()->first() : null;
            $booking = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $current = Payment::whereKey($old->id)->lockForUpdate()->firstOrFail();

            if ($booking->user_id !== $user->id) {
                throw new PaymentException('Pesanan ini bukan milik Anda.');
            }
            if ($booking->status !== Booking::PENDING || ! $hold || ! $hold->isUsable()) {
                throw new PaymentException('Pesanan sudah tidak dapat diubah karena waktunya habis atau statusnya berubah.');
            }
            if (! in_array($current->status, [Payment::PENDING, Payment::FAILED], true)) {
                throw new PaymentException('Tagihan ini tidak dapat diganti.');
            }

            Payment::where('booking_id', $booking->id)->where('status', Payment::PENDING)
                ->lockForUpdate()->get()->each(function (Payment $p) {
                    $p->update(['status' => Payment::CANCELLED, 'failure_reason' => 'Diganti dengan metode lain']);
                    PaymentLedger::record($p, 'payment_replaced', Payment::PENDING, Payment::CANCELLED, 'system');
                });

            $attempt = Payment::where('booking_id', $booking->id)->count() + 1;

            return $this->makePayment($booking, $hold, $methodCode, 'pay:'.$booking->booking_code.':'.$attempt, $hold->expires_at);
        }, 3);
    }

    /**
     * Terapkan hasil yang SUDAH terverifikasi di server (webhook sah atau simulasi lewat backend).
     *
     * @param string $status paid | pending | failed | expired
     * @return string paid | already_paid | reconciliation | failed | expired | pending | ignored | duplicate
     */
    public function handleResult(Payment $payment, string $status, string $source, ?string $eventId = null, array $payload = []): string
    {
        return match ($status) {
            'paid' => $this->finalizer->finalize($payment, $source, $payload, $eventId),
            'failed' => $this->markFailed($payment, $source, (string) ($payload['reason'] ?? 'Pembayaran ditolak'), $eventId, $payload),
            'expired' => $this->expireWithEvent($payment, $source, $eventId, $payload),
            'pending' => $this->recordOnly($payment, 'pending_received', $source, $eventId, $payload),
            default => throw new PaymentException('Status hasil pembayaran tidak dikenal.'),
        };
    }

    public function markFailed(Payment $payment, string $source, string $reason, ?string $eventId = null, array $payload = []): string
    {
        try {
            return DB::transaction(function () use ($payment, $source, $reason, $eventId, $payload) {
                $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if ($eventId) {
                    PaymentLedger::record($p, 'failure_received', $p->status, null, $source, $payload, $eventId);
                }
                if ($p->status !== Payment::PENDING) {
                    return 'ignored'; // misalnya sudah Paid: kabar gagal yang terlambat diabaikan
                }

                $p->update(['status' => Payment::FAILED, 'failed_at' => now(), 'failure_reason' => $reason]);
                PaymentLedger::record($p, 'payment_failed', Payment::PENDING, Payment::FAILED, $source, ['reason' => $reason]);

                return 'failed';
            });
        } catch (QueryException $e) {
            if ($eventId && $this->isDuplicate($e)) {
                return 'duplicate';
            }
            throw $e;
        }
    }

    /** Kedaluwarsakan tagihan: melepas hold, dan efek bertingkatnya menutup booking. */
    public function expire(Payment $payment, string $source): string
    {
        $payment = $payment->fresh();
        if ($payment->status !== Payment::PENDING) {
            return 'ignored';
        }

        $hold = $payment->booking?->hold;
        if ($hold) {
            $this->holds->release($hold, Hold::EXPIRED, 'Pembayaran kedaluwarsa');
        }

        // Cadangan bila tidak ada hold yang bisa dilepas.
        DB::transaction(function () use ($payment, $source) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->status === Payment::PENDING) {
                $p->update(['status' => Payment::EXPIRED, 'failed_at' => now(), 'failure_reason' => 'Batas waktu pembayaran habis']);
                PaymentLedger::record($p, 'payment_expired', Payment::PENDING, Payment::EXPIRED, $source);
            }
        });

        return 'expired';
    }

    private function expireWithEvent(Payment $payment, string $source, ?string $eventId, array $payload): string
    {
        if ($eventId) {
            try {
                PaymentLedger::record($payment, 'expiry_received', $payment->status, null, $source, $payload, $eventId);
            } catch (QueryException $e) {
                if ($this->isDuplicate($e)) {
                    return 'duplicate';
                }
                throw $e;
            }
        }

        return $this->expire($payment, $source);
    }

    private function recordOnly(Payment $payment, string $type, string $source, ?string $eventId, array $payload): string
    {
        try {
            PaymentLedger::record($payment, $type, $payment->status, null, $source, $payload, $eventId);
        } catch (QueryException $e) {
            if ($eventId && $this->isDuplicate($e)) {
                return 'duplicate';
            }
            throw $e;
        }

        return 'pending';
    }

    private function unique(string $column, string $prefix, int $length): string
    {
        do {
            $value = $prefix.strtoupper(Str::random($length));
        } while (Payment::where($column, $value)->exists());

        return $value;
    }

    private function isDuplicate(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}