<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * KERANGKA production. Hanya verifikasi webhook yang tersedia.
 * Pembuatan tagihan Midtrans BELUM diimplementasikan dan belum diuji (butuh kredensial resmi Anda).
 */
class MidtransProvider implements PaymentProviderInterface
{
    public function name(): string
    {
        return 'midtrans';
    }

    public function isSimulation(): bool
    {
        return false;
    }

    public function createCharge(Payment $payment, array $method): array
    {
        throw new PaymentException('Mode production belum diaktifkan. Integrasi pembuatan tagihan Midtrans belum dibuat. Gunakan PAYMENT_MODE=demo untuk pengujian lokal.');
    }

    public function parseWebhook(Request $request): array
    {
        $key = (string) config('payment.midtrans.server_key');
        if ($key === '') {
            throw new PaymentException('Kunci server Midtrans belum dikonfigurasi.');
        }

        $p = $request->json()->all();
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $field) {
            if (! isset($p[$field]) || ! is_scalar($p[$field])) {
                throw new PaymentException('Isi webhook tidak lengkap.');
            }
        }

        // Tanda tangan resmi Midtrans: SHA512(order_id + status_code + gross_amount + server_key)
        $expected = hash('sha512', $p['order_id'].$p['status_code'].$p['gross_amount'].$key);
        if (! hash_equals($expected, (string) $p['signature_key'])) {
            throw new PaymentException('Tanda tangan webhook tidak valid.');
        }

        $status = match ($p['transaction_status']) {
            'settlement' => 'paid',
            'capture' => (($p['fraud_status'] ?? 'accept') === 'accept') ? 'paid' : 'pending',
            'deny', 'cancel', 'failure' => 'failed',
            'expire' => 'expired',
            default => 'pending',
        };

        // Hanya simpan field aman. Tanda tangan dan data kartu tidak disimpan.
        $safe = Arr::only($p, [
            'order_id', 'transaction_id', 'transaction_status', 'status_code',
            'gross_amount', 'payment_type', 'fraud_status', 'transaction_time', 'currency',
        ]);

        return [
            'order_id' => (string) $p['order_id'],
            'status' => $status,
            'amount' => (int) explode('.', (string) $p['gross_amount'])[0],
            'event_id' => ($p['transaction_id'] ?? $p['order_id']).'|'.$p['transaction_status'].'|'.($p['fraud_status'] ?? ''),
            'payload' => $safe,
        ];
    }
}