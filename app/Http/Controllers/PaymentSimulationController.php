<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payment\PaymentService;
use App\Support\PaymentLedger;
use App\Support\PaymentMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * SIMULASI. Hanya aktif bila PAYMENT_MODE=demo DAN APP_ENV=local.
 * Tombol di halaman memanggil endpoint ini lewat POST bertoken CSRF.
 * Hasil tetap melewati servis backend yang sama dengan pembayaran sungguhan.
 */
class PaymentSimulationController extends Controller
{
    public function store(Request $request, Payment $payment, PaymentService $payments)
    {
        abort_unless(PaymentMode::simulationAllowed() && $payment->is_simulated, 404);
        Gate::authorize('view', $payment);

        $data = $request->validate([
            'action' => ['required', Rule::in(['success', 'fail', 'pending', 'expire', 'late'])],
        ]);

        $source = 'demo_simulation';

        $outcome = match ($data['action']) {
            'success' => $payments->handleResult($payment, 'paid', $source, null, ['action' => 'success']),
            'fail' => $payments->handleResult($payment, 'failed', $source, null, ['reason' => 'Simulasi pembayaran gagal']),
            'pending' => $this->pending($payments, $payment, $source),
            'expire' => $payments->expire($payment, $source),
            // Pembayaran sukses yang tiba setelah tagihan kedaluwarsa.
            'late' => $this->late($payments, $payment, $source),
        };

        [$type, $message] = match ($outcome) {
            'paid' => ['success', 'Simulasi berhasil: pembayaran terverifikasi dan pesanan dikonfirmasi.'],
            'already_paid' => ['success', 'Tagihan ini sudah berstatus Paid. Tidak ada perubahan.'],
            'reconciliation' => ['error', 'Pembayaran terverifikasi tetapi terlambat. Status: Reconciliation Required. Pesanan tidak dikonfirmasi otomatis.'],
            'failed' => ['error', 'Simulasi pembayaran gagal. Anda dapat mencoba metode lain selama waktu masih ada.'],
            'expired' => ['error', 'Simulasi kedaluwarsa: pesanan ditutup dan inventori dilepas.'],
            'pending' => ['success', 'Simulasi menunggu: status tetap Pending.'],
            default => ['error', 'Tidak ada perubahan pada tagihan.'],
        };

                // Pembayaran berhasil: langsung ke halaman receipt.
        if (in_array($outcome, ['paid', 'already_paid'], true)) {
            $receipt = $payment->fresh()->receipt;

            if ($receipt) {
                return redirect()->route('receipts.show', $receipt->receipt_number)->with($type, $message);
            }
        }

        return redirect()->route('payments.show', $payment->payment_reference)->with($type, $message);
    }

    private function pending(PaymentService $payments, Payment $payment, string $source): string
    {
        PaymentLedger::record($payment, 'simulated_pending', $payment->status, null, $source);

        return 'pending';
    }

    private function late(PaymentService $payments, Payment $payment, string $source): string
    {
        $payments->expire($payment, $source);

        return $payments->handleResult($payment->fresh(), 'paid', $source, null, ['action' => 'late']);
    }
}