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

/** Webhook penyedia pembayaran. Mode demo menolaknya (404). */
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

        $apiVerified = false;

        // Tanda tangan tidak cocok: isi webhook hanya petunjuk. Putusan dari API Midtrans (kunci server kita).
        if (! ($event['signature_ok'] ?? false)) {
            if (! $gateway instanceof MidtransProvider || empty($event['transaction_id'])) {
                return response()->json(['message' => 'ditolak'], 403);
            }

            try {
                $remote = $gateway->lookup([$event['transaction_id']]);
            } catch (PaymentException $e) {
                Log::warning('Webhook tanpa tanda tangan sah ditolak: '.$e->getMessage());

                return response()->json(['message' => 'ditolak'], 403);
            }

            if (! is_array($remote) || ! $gateway->matches($remote, $payment)) {
                PaymentLedger::record($payment, 'webhook_unverified', $payment->status, null, 'webhook', ['transaction_id' => $event['transaction_id']]);

                return response()->json(['message' => 'ditolak'], 403);
            }

            $event['status'] = $gateway->statusOf($remote);
            $event['amount'] = (int) explode('.', (string) $remote['gross_amount'])[0];
            $event['event_id'] = ($remote['transaction_id'] ?? $event['transaction_id']).'|'.($remote['transaction_status'] ?? '').'|'.($remote['fraud_status'] ?? '');
            $event['payload'] = $gateway->safe($remote) + ['confirmation' => 'api_only'];
            $apiVerified = true;
        }

        if ((int) $payment->amount !== (int) $event['amount']) {
            PaymentLedger::record($payment, 'amount_mismatch', $payment->status, null, 'webhook', [
                'diharapkan' => (int) $payment->amount, 'diterima' => (int) $event['amount'],
            ]);

            return response()->json(['message' => 'nominal tidak cocok'], 422);
        }

        // Simpan id transaksi agar pencarian status berikutnya bisa memakainya.
        if (! empty($event['transaction_id']) && ! $payment->provider_reference) {
            $payment->update(['provider_reference' => $event['transaction_id']]);
        }

        if ($event['status'] === 'paid' && ! $apiVerified && $gateway instanceof MidtransProvider) {
            $remote = false;
            try {
                $remote = $gateway->lookup([$event['transaction_id'] ?? '', $event['order_id']]);
            } catch (PaymentException $e) {
                // API status tidak terjangkau: lihat strict_confirm di bawah.
            }

            if (is_array($remote)) {
                if (! $gateway->confirmsPaid($remote, $payment)) {
                    PaymentLedger::record($payment, 'paid_not_confirmed', $payment->status, null, 'webhook', [
                        'transaction_id' => $event['transaction_id'] ?? null, 'status_midtrans' => $remote['transaction_status'] ?? null,
                    ]);

                    return response()->json(['message' => 'belum terkonfirmasi'], 422);
                }
                $event['payload']['confirmation'] = 'api_confirmed';
            } elseif (config('payment.midtrans.strict_confirm')) {
                return response()->json(['message' => 'konfirmasi tertunda'], 503);
            } else {
                $event['payload']['confirmation'] = 'signature_only';
            }
        }

        $outcome = $payments->handleResult($payment, $event['status'], 'webhook', $event['event_id'], $event['payload']);

        return response()->json(['message' => $outcome]);
    }
}