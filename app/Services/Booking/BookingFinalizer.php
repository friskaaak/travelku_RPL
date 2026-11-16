<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\FlightSeat;
use App\Models\Hold;
use App\Models\Payment;
use App\Models\RoomNight;
use App\Models\TrainSeat;
use App\Models\DiscountRedemption;
use App\Services\Discount\DiscountService;
use App\Support\PaymentLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * FINALISASI IDEMPOTEN setelah pembayaran terverifikasi.
 *
 * Satu transaksi, urutan kunci: hold, booking, tagihan.
 * Hasil:
 *  paid           : berhasil dikonfirmasi
 *  already_paid   : sudah Paid sebelumnya, tidak ada perubahan
 *  reconciliation : uang diterima tetapi pesanan tidak bisa dikonfirmasi otomatis
 *  ignored        : status tidak memerlukan tindakan
 *  duplicate      : event penyedia yang sama sudah pernah diproses
 */
class BookingFinalizer
{
          public function __construct(
        private DiscountService $discounts,
        private DocumentService $documents,
        private EmailDeliveryService $emails,
    ) {
    }

    public function finalize(Payment $payment, string $source, array $payload = [], ?string $eventId = null): string
    {
        try {
            return DB::transaction(function () use ($payment, $source, $payload, $eventId) {
                $bookingId = Payment::whereKey($payment->id)->value('booking_id');
                $holdId = Booking::whereKey($bookingId)->value('hold_id');

                $hold = $holdId ? Hold::whereKey($holdId)->lockForUpdate()->first() : null;
                $booking = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
                $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

                // Catat event penyedia lebih dulu. Event yang sama ditolak database (unique).
                if ($eventId) {
                    PaymentLedger::record($p, 'verification_received', $p->status, null, $source, $payload, $eventId);
                }

                if ($p->status === Payment::PAID) {
                    return 'already_paid';
                }
                if (in_array($p->status, [Payment::RECONCILIATION, Payment::REFUNDED], true)) {
                    return 'ignored';
                }

                $reason = null;
                if ($p->status !== Payment::PENDING) {
                    $reason = "Pembayaran terverifikasi saat status tagihan {$p->status}";
                } elseif (! $hold || $hold->status !== Hold::ACTIVE) {
                    $reason = 'Penahanan sudah tidak aktif';
                } elseif ($hold->expires_at->lte(now()) || $p->expires_at->lte(now())) {
                    $reason = 'Batas waktu pembayaran telah lewat';
                } elseif ($booking->status !== Booking::PENDING) {
                    $reason = 'Status pesanan bukan Pending';
                } elseif (! $this->inventoryIntact($hold)) {
                    $reason = 'Inventori tidak lagi tertahan untuk pesanan ini';
                }

                // Uang terverifikasi tetapi pesanan tidak bisa dikonfirmasi: JANGAN alokasikan diam-diam.
                if ($reason) {
                    $from = $p->status;
                    $p->update(['status' => Payment::RECONCILIATION, 'verified_at' => now(), 'failure_reason' => $reason]);
                    PaymentLedger::record($p, 'reconciliation_required', $from, Payment::RECONCILIATION, $source, ['reason' => $reason]);

                    return 'reconciliation';
                }

                // ---- Konfirmasi ----
                RoomNight::where('hold_id', $hold->id)->where('status', 'held')
                    ->update(['status' => 'confirmed', 'booking_id' => $booking->id, 'expires_at' => null]);

                foreach ([FlightSeat::class, TrainSeat::class] as $model) {
                    $model::where('hold_id', $hold->id)->where('status', 'held')
                        ->update(['status' => 'confirmed', 'booking_id' => $booking->id, 'held_until' => null]);
                }

                $booking->update(['status' => Booking::CONFIRMED, 'confirmed_at' => now()]);
                $p->update(['status' => Payment::PAID, 'paid_at' => now(), 'verified_at' => now()]);
                $hold->update(['status' => Hold::CONSUMED, 'consumed_at' => now()]);

                DiscountRedemption::where('hold_id', $hold->id)->where('status', 'reserved')->get()
                    ->each(fn ($r) => $this->discounts->markRedeemed($r, $booking->id));

                PaymentLedger::record($p, 'payment_paid', Payment::PENDING, Payment::PAID, $source, $payload);

                // Receipt dan tiket dibuat di transaksi yang sama (idempoten).
                $this->documents->issue($booking, $p);

                // Email diantrekan. Kegagalan antrean TIDAK boleh membatalkan konfirmasi pembayaran.
                $this->queueEmails($booking);

                return 'paid';
            }, 3);
        } catch (QueryException $e) {
            if ($eventId && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                return 'duplicate';
            }
            throw $e;
        }
    }

        /** Buat catatan email lalu antrekan setelah commit. Galat dilaporkan, bukan dilempar. */
    private function queueEmails(Booking $booking): void
    {
        try {
            $ids = $this->emails->createFor($booking->fresh());

            if ($ids) {
                // Pekerjaan masuk antrean HANYA setelah transaksi berhasil di-commit.
                DB::afterCommit(fn () => $this->emails->dispatch($ids));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Pastikan semua inventori hold ini masih berstatus 'held' (dikunci). */
    private function inventoryIntact(Hold $hold): bool
    {
        $d = $hold->details;

        return match ($hold->service_type) {
            'hotel' => RoomNight::where('hold_id', $hold->id)->where('status', 'held')->lockForUpdate()->pluck('id')->count()
                === count($d['rooms'] ?? []) * (int) ($d['nights'] ?? 0),
            'flight' => FlightSeat::where('hold_id', $hold->id)->where('status', 'held')->lockForUpdate()->pluck('id')->count()
                === count($d['seats'] ?? []),
            'train' => TrainSeat::where('hold_id', $hold->id)->where('status', 'held')->lockForUpdate()->pluck('id')->count()
                === count($d['seats'] ?? []),
            default => false,
        };
    }
}