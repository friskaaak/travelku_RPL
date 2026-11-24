<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Midtrans Snap, hanya DANA. Sandbox bila MIDTRANS_IS_PRODUCTION=false.
 * Keberhasilan HANYA dari webhook bertanda tangan yang dikonfirmasi ulang lewat API status.
 */
class MidtransProvider implements PaymentProviderInterface
{
    public function name(): string
    {
        return 'midtrans';
    }

    /** Sandbox = simulasi: tidak ada uang sungguhan. */
    public function isSimulation(): bool
    {
        return ! (bool) config('payment.midtrans.is_production');
    }

    public function createCharge(Payment $payment, array $method): array
    {
        if ($payment->method_code !== 'dana') {
            throw new PaymentException('Metode ini belum tersedia. Pilih DANA.');
        }

        $booking = $payment->booking;
        $secs = $payment->expires_at->timestamp - now()->timestamp;
        $minutes = max(15, min(1440, (int) ceil($secs / 60))); // DANA: minimal 15 menit, maksimal 1 hari

        try {
            $res = Http::withBasicAuth($this->serverKey(), '')
                ->acceptJson()->timeout(20)
                ->post($this->snapUrl(), [
                    'transaction_details' => ['order_id' => $payment->payment_reference, 'gross_amount' => (int) $payment->amount],
                    'item_details' => [[
                        'id' => $booking->booking_code,
                        'price' => (int) $payment->amount,
                        'quantity' => 1,
                        'name' => Str::limit($booking->title, 45, ''),
                    ]],
                    'customer_details' => [
                        'first_name' => Str::limit($booking->contact_name, 50, ''),
                        'email' => $booking->contact_email,
                    ],
                    'enabled_payments' => ['dana'],
                    'dana' => ['callback_url' => route('payments.show', $payment->payment_reference)],
                    'expiry' => ['unit' => 'minute', 'duration' => $minutes],
                ]);
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Midtrans tidak terjangkau: '.class_basename($e));
            throw new PaymentException('Tidak dapat menghubungi penyedia pembayaran. Coba lagi sebentar lagi.');
        }

        $url = (string) $res->json('redirect_url');

        if (! $res->successful() || ! str_starts_with($url, 'https://')) {
            Log::warning('Midtrans menolak transaksi', ['status' => $res->status(), 'pesan' => $res->json('error_messages')]);
            throw new PaymentException('Penyedia pembayaran menolak permintaan. Periksa kunci sandbox dan pengaturan DANA di dashboard Midtrans.');
        }

        return [
            'provider_reference' => null,
            'payment_code' => null,
            'qr_payload' => null,
            'redirect_url' => $url,
            'instructions' => [
                'redirect_url' => $url,
                'groups' => [['title' => 'Bayar dengan DANA', 'steps' => [
                    'Tekan tombol Bayar dengan DANA.',
                    'Anda diarahkan ke aplikasi DANA atau tampilan web DANA, tergantung aplikasi terpasang.',
                    'Selesaikan pembayaran di sana.',
                    'Anda kembali ke halaman ini. Status berubah setelah Midtrans mengonfirmasi pembayaran.',
                ]]],
            ],
        ];
    }

    /** Baca dan verifikasi tanda tangan webhook. */
    public function parseWebhook(Request $request): array
    {
        $key = $this->serverKey();
        $p = $request->json()->all();

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $field) {
            if (! isset($p[$field]) || ! is_scalar($p[$field])) {
                throw new PaymentException('Isi webhook tidak lengkap.');
            }
        }

        $expected = hash('sha512', $p['order_id'].$p['status_code'].$p['gross_amount'].$key);
        if (! hash_equals($expected, (string) $p['signature_key'])) {
            throw new PaymentException('Tanda tangan webhook tidak valid.');
        }

        return [
            'order_id' => (string) $p['order_id'],
            'transaction_id' => (string) ($p['transaction_id'] ?? ''),
            'status' => self::map((string) $p['transaction_status'], (string) ($p['fraud_status'] ?? 'accept')),
            'amount' => (int) explode('.', (string) $p['gross_amount'])[0],
            'event_id' => ($p['transaction_id'] ?? $p['order_id']).'|'.$p['transaction_status'].'|'.($p['fraud_status'] ?? ''),
            'payload' => $this->safe($p),
        ];
    }

    /**
     * Tanyakan status ke Midtrans. Null bila transaksi tidak ditemukan.
     * Respons wajib bertanda tangan sah.
     */
    public function fetchStatus(string $id): ?array
    {
        $key = $this->serverKey();

        try {
            $res = Http::withBasicAuth($key, '')
                ->acceptJson()
                ->timeout(15)
                ->get($this->apiBase().'/v2/'.rawurlencode($id).'/status');
        } catch (\Throwable $e) {
            Log::warning('Midtrans status gagal: '.class_basename($e));
            throw new PaymentException('Tidak dapat menghubungi Midtrans. Coba lagi sebentar lagi.');
        }

        $d = $res->json();
        if (! is_array($d)) {
            throw new PaymentException('Respons Midtrans tidak dapat dibaca.');
        }
        if ((string) ($d['status_code'] ?? '') === '404') {
            return null;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $f) {
            if (! isset($d[$f]) || ! is_scalar($d[$f])) {
                throw new PaymentException('Respons Midtrans tidak lengkap atau tidak dapat diverifikasi.');
            }
        }

        $expected = hash('sha512', $d['order_id'].$d['status_code'].$d['gross_amount'].$key);
        if (! hash_equals($expected, (string) $d['signature_key'])) {
            throw new PaymentException('Tanda tangan respons Midtrans tidak valid.');
        }

        return $d;
    }

    public function statusOf(array $d): string
    {
        return self::map((string) ($d['transaction_status'] ?? ''), (string) ($d['fraud_status'] ?? 'accept'));
    }

    /** Respons status benar-benar menyatakan SUKSES untuk tagihan ini. */
    public function confirmsPaid(array $remote, Payment $payment): bool
    {
        return ($remote['order_id'] ?? null) === $payment->payment_reference
            && (int) explode('.', (string) ($remote['gross_amount'] ?? '0'))[0] === (int) $payment->amount
            && $this->statusOf($remote) === 'paid';
    }

    public function safe(array $p): array
    {
        return Arr::only($p, ['order_id', 'transaction_id', 'transaction_status', 'status_code', 'gross_amount', 'payment_type', 'fraud_status', 'transaction_time', 'currency']);
    }

    public static function map(string $status, string $fraud): string
    {
        return match ($status) {
            'settlement' => 'paid',
            'capture' => $fraud === 'accept' ? 'paid' : 'pending',
            'deny', 'cancel', 'failure' => 'failed',
            'expire' => 'expired',
            default => 'pending',
        };
    }

    private function serverKey(): string
    {
        $key = (string) config('payment.midtrans.server_key');
        if ($key === '') {
            throw new PaymentException('Kunci server Midtrans belum diisi di .env.');
        }

        return $key;
    }

    private function snapUrl(): string
    {
        return config('payment.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    private function apiBase(): string
    {
        return config('payment.midtrans.is_production') ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }
}