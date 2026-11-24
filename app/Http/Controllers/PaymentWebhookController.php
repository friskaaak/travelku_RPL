<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Services\Payment\MidtransProvider;
use App\Services\Payment\PaymentProviderInterface;
use App\Services\Payment\PaymentService;
use App\Support\PaymentLedger;
use App\Support\PaymentMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Webhook penyedia pembayaran. Mode demo menolaknya (404). Keamanan lewat tanda tangan, bukan CSRF. */
class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider, PaymentProviderInterface $gateway, PaymentService $payments)
    {
        abort_if(PaymentMode::isDemo(), 404);
        abort_unless($provider === config('payment.provider'), 404);

        try {
            $event = $gateway->parseWebhook($request);
        } catch (PaymentException $e) {
            Log::warning('Webhook pembayaran ditolak: '.$e->getMessage(), ['ip' => $request->ip()]);

            return response()->json(['message' => 'ditolak'], 403);
        }

        $payment = Payment::where('payment_reference', $event['order_id'])->first();
        if (! $payment) {
            return response()->json(['message' => 'tagihan tidak dikenal'], 404);
        }

        if ((int) $payment->amount !== (int) $event['amount']) {
            PaymentLedger::record($payment, 'amount_mismatch', $payment->status, null, 'webhook', [
                'diharapkan' => (int) $payment->amount, 'diterima' => (int) $event['amount'],
            ]);

            return response()->json(['message' => 'nominal tidak cocok'], 422);
        }

        // Sukses harus dikonfirmasi ulang langsung ke Midtrans sebelum pesanan difinalisasi.
        if ($event['status'] === 'paid' && $gateway instanceof MidtransProvider) {
            try {
                $remote = $gateway->fetchStatus($event['transaction_id'] ?: $event['order_id']);
            } catch (PaymentException $e) {
                return response()->json(['message' => 'konfirmasi tertunda'], 503); // Midtrans akan mengirim ulang
            }

            if (! $remote || ! $gateway->confirmsPaid($remote, $payment)) {
                PaymentLedger::record($payment, 'paid_not_confirmed', $payment->status, null, 'webhook', ['transaction_id' => $event['transaction_id'] ?? null]);

                return response()->json(['message' => 'belum terkonfirmasi'], 422);
            }
        }

        $outcome = $payments->handleResult($payment, $event['status'], 'webhook', $event['event_id'], $event['payload']);

        return response()->json(['message' => $outcome]);
    }
}