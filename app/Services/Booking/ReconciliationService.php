<?php

namespace App\Services\Booking;

use App\Exceptions\CancellationException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditService;
use App\Support\PaymentLedger;
use App\Support\PaymentMode;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    /** Selesaikan Reconciliation Required dengan refund penuh. */
    public function refund(Payment $payment, User $admin, ?string $note): void
    {
        DB::transaction(function () use ($payment, $admin, $note) {
            $bookingId = Payment::whereKey($payment->id)->value('booking_id');
            $b = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($p->status !== Payment::RECONCILIATION) {
                throw new CancellationException('Tagihan ini tidak berstatus Reconciliation Required.');
            }

            $amount = (int) $p->amount;
            $status = PaymentMode::isDemo() ? 'simulated' : 'pending';
            $note = $note ? mb_substr(trim($note), 0, 200) : null;

            $p->update(['status' => Payment::REFUNDED, 'refunded_amount' => $amount]);

            $b->update(array_merge(
                ['refund_amount' => $amount, 'refund_status' => $status, 'penalty_amount' => 0],
                $b->status === Booking::PENDING
                    ? ['status' => Booking::CANCELLED, 'cancelled_at' => now(), 'cancellation_reason' => 'Pembayaran terlambat, dikembalikan']
                    : []
            ));

            PaymentLedger::record($p, 'reconciliation_refunded', Payment::RECONCILIATION, Payment::REFUNDED, 'admin', [
                'admin_id' => $admin->id, 'note' => $note, 'refund_status' => $status,
            ]);

            AuditService::log('payment.reconciliation_refunded', $p, [
                'booking' => $b->booking_code, 'amount' => $amount, 'note' => $note,
            ]);
        }, 3);
    }

    /** Tandai refund production sudah dikembalikan lewat penyedia pembayaran. */
    public function markRefundProcessed(Booking $booking, User $admin): void
    {
        DB::transaction(function () use ($booking, $admin) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($b->refund_status !== 'pending') {
                throw new CancellationException('Refund pesanan ini tidak berstatus menunggu diproses.');
            }

            $b->update(['refund_status' => 'processed']);

            $p = Payment::where('booking_id', $b->id)->where('status', Payment::REFUNDED)->latest('id')->first();
            if ($p) {
                PaymentLedger::record($p, 'refund_processed', Payment::REFUNDED, Payment::REFUNDED, 'admin', [
                    'admin_id' => $admin->id, 'amount' => (int) $b->refund_amount,
                ]);
            }

            AuditService::log('booking.refund_processed', $b, ['booking' => $b->booking_code, 'amount' => (int) $b->refund_amount]);
        }, 3);
    }
}