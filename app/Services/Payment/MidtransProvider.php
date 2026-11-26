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

                Log::info('Midtrans createCharge', ['order_id' => $payment->payment_reference, 'sidik_kunci' => $this->fingerprint()]);
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
        $this->serverKey();
        $raw = (string) $request->getContent();
        $p = json_decode($raw, true);

        if (! is_array($p)) {
            throw new PaymentException('Isi webhook bukan JSON yang valid.');
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $field) {
            if (! isset($p[$field]) || ! is_scalar($p[$field])) {
                throw new PaymentException('Isi webhook tidak lengkap.');
            }
        }

        $gross = $this->rawField($raw, 'gross_amount') ?? (string) $p['gross_amount'];
        $ok = $this->signatureOk((string) $p['order_id'], (string) $p['status_code'], $gross, (string) $p['signature_key']);

        if (! $ok) {
            $this->logSignatureFailure($p, $gross);
        }

        return [
            'order_id' => (string) $p['order_id'],
            'transaction_id' => (string) ($p['transaction_id'] ?? ''),
            'status' => $this->webhookStatus($p),
            'amount' => (int) explode('.', $gross)[0],
            'event_id' => ($p['transaction_id'] ?? $p['order_id']).'|'.$p['transaction_status'].'|'.($p['fraud_status'] ?? ''),
            'payload' => $this->safe($p),
            'signature_ok' => $ok,
        ];
    }

        private function webhookStatus(array $p): string
    {
        $s = self::map((string) $p['transaction_status'], (string) ($p['fraud_status'] ?? 'accept'));

        // Sukses hanya diterima bila kode status 200.
        return ($s === 'paid' && (string) $p['status_code'] !== '200') ? 'pending' : $s;
    }

    /**
     * Tanyakan status ke Midtrans. Null bila transaksi tidak ditemukan.
     * Respons wajib bertanda tangan sah.
     */
        /**
     * Cari transaksi di Midtrans. Mencoba setiap id (nomor order, lalu id transaksi)
     * dengan dan tanpa header transaction-source. Null bila semuanya 404.
     */

            /** Tanda tangan sah bila cocok dengan salah satu format penulisan nominal yang sama nilainya. */
    private function signatureOk(string $order, string $code, string $gross, string $received): bool
    {
        $key = $this->serverKey();

        foreach (array_unique([$gross, explode('.', $gross)[0], number_format((float) $gross, 2, '.', '')]) as $g) {
            if (hash_equals(hash('sha512', $order.$code.$g.$key), $received)) {
                return true;
            }
        }

        return false;
    }

    private function rawField(string $raw, string $field): ?string
    {
        return preg_match('/"'.preg_quote($field, '/').'"\s*:\s*"?(-?[0-9]+(?:\.[0-9]+)?)"?/', $raw, $m) ? $m[1] : null;
    }

    /** Diagnosa tanpa rahasia. Hanya 7 huruf awal kunci dan panjangnya. */
    /** Diagnosa tanpa rahasia: sidik kunci dan 12 karakter awal tanda tangan. */
    private function logSignatureFailure(array $p, string $gross): void
    {
        $key = $this->serverKey();
        $calc = [];

        foreach (array_unique([$gross, explode('.', $gross)[0], number_format((float) $gross, 2, '.', '')]) as $g) {
            $calc[$g] = substr(hash('sha512', $p['order_id'].$p['status_code'].$g.$key), 0, 12);
        }

        Log::warning('Diagnosa tanda tangan webhook', [
            'order_id' => $p['order_id'], 'transaction_id' => $p['transaction_id'] ?? null,
            'status_code' => $p['status_code'], 'gross_amount_teks_asli' => $gross,
            'transaction_status' => $p['transaction_status'], 'payment_type' => $p['payment_type'] ?? null,
            'merchant_id' => $p['merchant_id'] ?? null, 'sidik_kunci' => $this->fingerprint(),
            'tanda_tangan_diterima_awal' => substr((string) $p['signature_key'], 0, 12),
            'tanda_tangan_dihitung_awal' => $calc,
        ]);
    }
        public function lookup(array $ids): ?array
    {
        $key = $this->serverKey();

        foreach (array_values(array_unique(array_filter($ids))) as $id) {
            foreach ([true, false] as $withSource) {
                $headers = ['Accept' => 'application/json'] + ($withSource ? ['transaction-source' => 'SNAP_API'] : []);

                try {
                    $res = Http::withBasicAuth($key, '')->withHeaders($headers)->timeout(15)
                        ->get($this->apiBase().'/v2/'.rawurlencode($id).'/status');
                } catch (\Throwable $e) {
                    Log::warning('Midtrans status gagal: '.class_basename($e));
                    throw new PaymentException('Tidak dapat menghubungi Midtrans. Coba lagi sebentar lagi.');
                }

                $d = $res->json();
                $code = is_array($d) ? (string) ($d['status_code'] ?? '') : '';

                Log::info('Midtrans status lookup', [
                    'id' => $id, 'header_source' => $withSource, 'http' => $res->status(),
                    'status_code' => $code, 'transaction_status' => is_array($d) ? ($d['transaction_status'] ?? null) : null,
                ]);

                if ($code === '401' || $res->status() === 401) {
                    throw new PaymentException('Kunci server Midtrans ditolak (401). Periksa MIDTRANS_SERVER_KEY dan lingkungan sandbox atau production.');
                }
                if ($code === '401' || $res->status() === 401) {
                    throw new PaymentException($this->probeKey()
                        ? 'Midtrans menolak akses ke id ini (401). Kunci server valid, jadi id transaksi salah atau transaksi milik akun Midtrans lain.'
                        : 'Kunci server Midtrans ditolak (401). Periksa MIDTRANS_SERVER_KEY dan lingkungan sandbox atau production.');
                }

                // Tanda tangan diperiksa bila ada. Respons datang langsung dari Midtrans lewat HTTPS.
                                // Respons diambil langsung dari Midtrans lewat HTTPS dengan kunci server kita, jadi tetap dipakai.
                // Ketidakcocokan hanya dicatat sebagai diagnosa.
                if (isset($d['signature_key'], $d['order_id'], $d['status_code'], $d['gross_amount'])
                    && ! $this->signatureOk((string) $d['order_id'], (string) $d['status_code'], (string) $d['gross_amount'], (string) $d['signature_key'])) {
                    Log::notice('Tanda tangan respons status tidak cocok rumus standar (respons tetap dipakai)', [
                        'order_id' => $d['order_id'], 'transaction_id' => $d['transaction_id'] ?? null,
                        'status_code' => $d['status_code'], 'payment_type' => $d['payment_type'] ?? null,
                    ]);
                }

                return $d;
            }
        }

        return null;
    }

    public function fetchStatus(string $id): ?array
    {
        return $this->lookup([$id]);
    }

    public function statusOf(array $d): string
    {
        return self::map((string) ($d['transaction_status'] ?? ''), (string) ($d['fraud_status'] ?? 'accept'));
    }

    /** Respons status benar-benar menyatakan SUKSES untuk tagihan ini. */
        /** Sidik kunci (10 karakter hash). Aman dibagikan, tidak bisa dibalik menjadi kunci. */
    public function fingerprint(): string
    {
        return substr(hash('sha256', (string) config('payment.midtrans.server_key')), 0, 10);
    }

    /** True bila Midtrans menerima kunci (id acak dijawab 404, bukan 401). */
    public function probeKey(): bool
    {
        $res = Http::withBasicAuth($this->serverKey(), '')->acceptJson()->timeout(15)
            ->get($this->apiBase().'/v2/tk-probe-'.Str::lower(Str::random(10)).'/status');

        return $res->status() !== 401 && (string) $res->json('status_code') !== '401';
    }

    /** Order dan nominal dari Midtrans cocok dengan tagihan kita. */
    public function matches(array $remote, Payment $payment): bool
    {
        return ($remote['order_id'] ?? null) === $payment->payment_reference
            && (int) explode('.', (string) ($remote['gross_amount'] ?? '0'))[0] === (int) $payment->amount;
    }

    public function confirmsPaid(array $remote, Payment $payment): bool
    {
        return $this->matches($remote, $payment) && $this->statusOf($remote) === 'paid';
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