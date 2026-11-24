<?php

namespace App\Support;

class PaymentMode
{
    public static function isDemo(): bool
    {
        return config('payment.mode') === 'demo';
    }

    /** Tombol simulasi hanya bila PAYMENT_MODE=demo DAN APP_ENV=local. */
    public static function simulationAllowed(): bool
    {
        return self::isDemo() && app()->environment('local');
    }

    /** Pada mode gateway hanya metode di live_methods yang aktif. */
    public static function isEnabled(string $code): bool
    {
        if (! isset(config('payment.methods')[$code])) {
            return false;
        }

        return self::isDemo() || in_array($code, (array) config('payment.live_methods', []), true);
    }

    public static function method(string $code): ?array
    {
        return self::isEnabled($code) ? config("payment.methods.$code") : null;
    }

    public static function codes(): array
    {
        return array_values(array_filter(array_keys(config('payment.methods')), fn ($c) => self::isEnabled($c)));
    }

    /** Biaya layanan (integer rupiah). */
    public static function fee(string $code, int $subtotalAfterDiscount): int
    {
        $m = self::method($code);
        if (! $m) {
            return 0;
        }

        $fixed = (int) ($m['fee'] ?? 0);
        $bps = (int) round(((float) ($m['fee_pct'] ?? 0)) * 100);

        return $fixed + intdiv($subtotalAfterDiscount * $bps, 10000);
    }
}