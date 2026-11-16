<?php

namespace App\Support;

class Money
{
    /** Format rupiah dari integer. Contoh: 1250000 menjadi Rp1.250.000 */
    public static function idr(int|float|string|null $value): string
    {
        return 'Rp'.number_format((int) $value, 0, ',', '.');
    }
}