<?php

namespace App\Support;

class Format
{
    /** 435 menjadi "7j 15m" */
    public static function duration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h === 0) {
            return "{$m}m";
        }

        return $m > 0 ? "{$h}j {$m}m" : "{$h}j";
    }
}