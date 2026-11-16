<?php

namespace App\Support;

class PaymentMode
{
    public static function isDemo(): bool
    {
        return config('payment.mode') === 'demo';
    }

    /**
     * Tombol simulasi hanya boleh muncul dan diproses bila:
     *  1. PAYMENT_MODE=demo, DAN
     *  2. APP_ENV=local (bukan staging/production).
     * Ini mencegah simulasi aktif tidak sengaja di server publik.
     */
    public static function simulationAllowed(): bool
    {
        return self::isDemo() && app()->environment('local');
    }

    public static function method(string $code): ?array
    {
        return config("payment.methods.$code");
    }

    public static function codes(): array
    {
        return array_keys(config('payment.methods'));
    }

    /**
     * Hitung biaya layanan di server (integer rupiah, tanpa float).
     */
    public static function fee(string $code, int $subtotalAfterDiscount): int
    {
        $m = self::method($code);
        if (! $m) {
            return 0;
        }

        $fixed = (int) ($m['fee'] ?? 0);
        // fee_pct dalam persen; hitung dengan basis per-10.000 agar tetap integer.
        $bps = (int) round(((float) ($m['fee_pct'] ?? 0)) * 100);
        $pct = intdiv($subtotalAfterDiscount * $bps, 10000);

        return $fixed + $pct;
    }
}