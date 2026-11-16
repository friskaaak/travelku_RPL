<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\Hold;
use App\Models\Payment;
use App\Services\Inventory\HoldService;
use App\Services\Payment\PaymentService;
use App\Support\PaymentMode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Services\Booking\DocumentService;

class PaymentController extends Controller
{
    public function __construct(private HoldService $holds, private PaymentService $payments)
    {
    }

    public function show(Payment $payment)
    {
        Gate::authorize('view', $payment);

        $booking = $payment->booking;
        $hold = $booking->hold;

        // Kedaluwarsa dicek di server setiap halaman dibuka.
        if ($hold && $booking->status === Booking::PENDING) {
            $this->holds->expireIfDue($hold);
            $payment->refresh();
            $booking->refresh();
            $hold->refresh();
        }

                // Pembayaran sudah berhasil: tampilkan receipt (dibuat bila belum ada, idempoten).
        if ($payment->status === Payment::PAID) {
            app(DocumentService::class)->ensureIssued($booking->fresh());

            if ($receipt = $payment->receipt()->first()) {
                return redirect()->route('receipts.show', $receipt->receipt_number);
            }
        }
        $canChange = $hold
            && $booking->status === Booking::PENDING
            && $hold->isUsable()
            && in_array($payment->status, [Payment::PENDING, Payment::FAILED], true);

        $latest = $booking->payments()->latest('id')->first();

        return view('payment.show', [
            'payment' => $payment,
            'booking' => $booking,
            'hold' => $hold,
            'canChange' => $canChange,
            'categories' => $canChange ? $this->payments->catalog((int) $hold->total_amount) : [],
            'latest' => $latest,
            'qr' => $this->qrSvg($payment->qr_payload),
            'simulation' => PaymentMode::simulationAllowed() && $payment->is_simulated,
        ]);
    }

    /** Dipakai halaman untuk mendeteksi perubahan status dari server. */
    public function status(Payment $payment)
    {
        Gate::authorize('view', $payment);

        $booking = $payment->booking;
        if ($booking->hold && $booking->status === Booking::PENDING) {
            $this->holds->expireIfDue($booking->hold);
        }

        return response()->json(['status' => $payment->fresh()->status]);
    }

    public function change(Request $request, Payment $payment)
    {
        Gate::authorize('view', $payment);

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(PaymentMode::codes())],
        ], [
            'payment_method.required' => 'Pilih metode pembayaran.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
        ]);

        try {
            $new = $this->payments->replaceMethod($payment, $request->user(), $data['payment_method']);
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            report($e);

            return back()->with('error', 'Permintaan ganda terdeteksi. Muat ulang halaman lalu coba lagi.');
        }

        return redirect()->route('payments.show', $new->payment_reference)
            ->with('success', 'Metode pembayaran diperbarui. Tagihan baru dibuat.');
    }

    public function cancel(Payment $payment)
    {
        Gate::authorize('view', $payment);

        $booking = $payment->booking;
        $hold = $booking->hold;

        if ($booking->status !== Booking::PENDING || ! $hold || $hold->status !== Hold::ACTIVE) {
            return back()->with('error', 'Pesanan ini tidak dapat dibatalkan.');
        }

        // Melepas hold otomatis menutup booking dan tagihan Pending.
        $this->holds->release($hold, Hold::RELEASED, 'Dibatalkan oleh pelanggan');

        return redirect()->route('home')->with('success', 'Pesanan dibatalkan. Kamar atau kursi dilepas.');
    }

    /** QR dibuat dari paket simple-qrcode bila terpasang. Isinya simulasi, bukan QRIS asli. */
    private function qrSvg(?string $payload): ?string
    {
        if (! $payload || ! class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            return null;
        }

        try {
            return (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(1)->generate($payload);
        } catch (\Throwable $e) {
            return null;
        }
    }
}