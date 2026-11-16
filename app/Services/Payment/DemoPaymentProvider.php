<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * SIMULASI LOKAL. Tidak ada uang yang bergerak, tidak ada bank atau dompet digital sungguhan.
 * Semua nomor dan kode dibuat acak dan tidak terdaftar di mana pun.
 */
class DemoPaymentProvider implements PaymentProviderInterface
{
    public function name(): string
    {
        return 'demo';
    }

    public function isSimulation(): bool
    {
        return true;
    }

    public function createCharge(Payment $payment, array $method): array
    {
        $amount = Money::idr($payment->amount);
        $base = [
            'provider_reference' => 'DEMO-'.strtoupper(Str::random(12)),
            'payment_code' => null,
            'qr_payload' => null,
        ];

        return match ($method['flow']) {
            'va' => $this->va($base, $method, $amount),
            'ewallet' => $this->ewallet($base, $method, $amount),
            'qris' => $this->qris($base, $payment),
            'card' => $this->card($base),
            'store' => $this->store($base, $method, $amount),
            default => throw new PaymentException('Jenis metode pembayaran tidak dikenal.'),
        };
    }

    public function parseWebhook(Request $request): array
    {
        throw new PaymentException('Webhook tidak aktif pada mode demo.');
    }

    private function va(array $base, array $method, string $amount): array
    {
        $bank = $method['bank'];
        $base['payment_code'] = $method['va_prefix'].str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $base['instructions'] = ['groups' => [
            ['title' => "ATM {$bank}", 'steps' => [
                'Pilih menu Transfer, lalu Virtual Account.',
                'Masukkan nomor Virtual Account di atas.',
                "Pastikan nominal {$amount}, lalu konfirmasi.",
                'Simpan struk sebagai bukti.',
            ]],
            ['title' => "Mobile banking {$bank}", 'steps' => [
                'Buka aplikasi mobile banking Anda.',
                'Pilih Transfer, lalu Virtual Account.',
                'Masukkan nomor Virtual Account dan periksa nominal.',
                'Konfirmasi di aplikasi bank Anda.',
            ]],
        ]];

        return $base;
    }

    private function ewallet(array $base, array $method, string $amount): array
    {
        $label = $method['label'];
        $base['payment_code'] = 'EW-'.strtoupper(Str::random(8));
        $base['instructions'] = ['groups' => [
            ['title' => "Bayar dengan {$label}", 'steps' => [
                "Buka aplikasi {$label} di ponsel Anda.",
                'Pilih Bayar, lalu masukkan referensi di atas.',
                "Periksa nominal {$amount}, lalu konfirmasi.",
                'Pada mode demo Anda tidak dialihkan ke aplikasi sungguhan. Gunakan tombol simulasi.',
            ]],
        ]];

        return $base;
    }

    private function qris(array $base, Payment $payment): array
    {
        // Sengaja BUKAN format QRIS/EMVCo, sehingga tidak bisa dipindai aplikasi pembayaran sungguhan.
        $base['qr_payload'] = 'TRAVELKITA-DEMO|QRIS-SIMULASI|'.$payment->invoice_number.'|'.$payment->amount;
        $base['instructions'] = ['groups' => [
            ['title' => 'Pindai QR simulasi', 'steps' => [
                'Pada pembayaran sungguhan, Anda memindai QR dengan aplikasi bank atau dompet digital.',
                'Kode QR di bawah hanya simulasi dan tidak bisa dipindai.',
                'Gunakan tombol simulasi untuk menentukan hasilnya.',
            ]],
        ]];

        return $base;
    }

    private function card(array $base): array
    {
        $base['instructions'] = ['groups' => [
            ['title' => 'Kartu debit atau kredit (simulasi)', 'steps' => [
                'Mode demo tidak meminta nomor kartu, CVV, atau PIN, dan tidak menyimpannya.',
                'Pilih hasil otorisasi kartu dengan tombol simulasi di bawah.',
                'Pada mode production, data kartu diproses langsung oleh penyedia pembayaran resmi, bukan aplikasi ini.',
            ]],
        ]];

        return $base;
    }

    private function store(array $base, array $method, string $amount): array
    {
        $label = $method['label'];
        $base['payment_code'] = $method['store_prefix'].str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $base['instructions'] = ['groups' => [
            ['title' => "Bayar di kasir {$label}", 'steps' => [
                "Datang ke gerai {$label} terdekat.",
                'Sebutkan kode pembayaran di atas kepada kasir.',
                "Bayar tunai sebesar {$amount} (belum termasuk biaya gerai bila berbeda).",
                'Simpan struk sebagai bukti. Pada mode demo ini hanya simulasi.',
            ]],
        ]];

        return $base;
    }
}