<?php

namespace App\Services\Booking;

use App\Exceptions\CancellationException;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\RoomNight;
use App\Models\User;
use App\Support\PaymentLedger;
use App\Support\PaymentMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CancellationService
{
    /** Hitung boleh tidaknya batal beserta biaya dan refund. Hanya membaca data. */
    public function quote(Booking $b): array
    {
        $no = fn (string $why) => ['allowed' => false, 'reason' => $why, 'free' => false, 'penalty' => 0, 'refund' => 0, 'deadline' => null];

        if ($b->status !== Booking::CONFIRMED) {
            return $no('Hanya pesanan terkonfirmasi yang dapat dibatalkan.');
        }
        if ($b->service_type !== 'hotel') {
            return $no((string) config('travel.cancellation.'.$b->service_type, 'Pesanan ini tidak dapat dibatalkan.'));
        }

        $d = $b->snapshot['details'] ?? [];
        $checkIn = Carbon::parse(($d['check_in'] ?? $b->start_date->toDateString()).' '.($d['check_in_time'] ?? '14:00'));

        if (now()->gte($checkIn)) {
            return $no('Waktu check-in sudah lewat, pesanan tidak dapat dibatalkan.');
        }

        $hours = (int) (Hotel::whereKey($d['hotel_id'] ?? 0)->value('cancellation_hours') ?? 24);
        $deadline = $checkIn->copy()->subHours($hours);
        $free = ! empty($d['free_cancellation']) && now()->lte($deadline);

        $total = (int) $b->total_amount;
        $nights = max(1, (int) ($d['nights'] ?? 1));
        $penalty = $free ? 0 : min($total, intdiv($total, $nights));

        return [
            'allowed' => true, 'reason' => null, 'free' => $free,
            'penalty' => $penalty, 'refund' => $total - $penalty,
            'deadline' => ! empty($d['free_cancellation']) ? $deadline : null,
        ];
    }

    /** Batalkan pesanan terbayar. Idempoten: pembatalan kedua ditolak. */
    public function cancel(Booking $booking, User $user, ?string $reason): array
    {
        return DB::transaction(function () use ($booking, $user, $reason) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($b->user_id !== $user->id) {
                throw new CancellationException('Pesanan ini bukan milik Anda.');
            }

            $q = $this->quote($b);
            if (! $q['allowed']) {
                throw new CancellationException($q['reason']);
            }

            $payment = Payment::where('booking_id', $b->id)->where('status', Payment::PAID)->latest('id')->lockForUpdate()->first();
            if (! $payment) {
                throw new CancellationException('Tagihan terbayar tidak ditemukan. Hubungi admin.');
            }

            $refund = $q['refund'];
            $status = $refund > 0 ? (PaymentMode::isDemo() ? 'simulated' : 'pending') : 'none';

            $b->update([
                'status' => Booking::CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason ? mb_substr(trim($reason), 0, 200) : 'Dibatalkan oleh pelanggan',
                'penalty_amount' => $q['penalty'],
                'refund_amount' => $refund,
                'refund_status' => $status,
            ]);

            RoomNight::where('booking_id', $b->id)->delete(); // kamar kembali tersedia

            if ($refund > 0) {
                $payment->update(['status' => Payment::REFUNDED, 'refunded_amount' => $refund]);
            }

            PaymentLedger::record($payment, 'booking_cancelled', Payment::PAID, $refund > 0 ? Payment::REFUNDED : Payment::PAID, 'customer', [
                'penalty' => $q['penalty'], 'refund' => $refund, 'refund_status' => $status,
            ]);

            return $q + ['refund_status' => $status];
        }, 3);
    }
}