<?php

namespace App\Support;

use Carbon\Carbon;

class DiscountTerms
{
    /** Susun teks syarat dan ketentuan dari aturan diskon. */
    public static function build(array $d): string
    {
        $names = ['hotel' => 'hotel', 'flight' => 'pesawat', 'train' => 'kereta'];
        $services = implode(', ', array_map(fn ($s) => $names[$s] ?? $s, $d['services'] ?? []));

        $t = ['Berlaku untuk '.$services.'.'];

        $scope = $d['scope_filter']['flight_scope'] ?? null;
        if ($scope === 'domestic') {
            $t[] = 'Khusus penerbangan domestik.';
        } elseif ($scope === 'international') {
            $t[] = 'Khusus penerbangan internasional.';
        }

        if ((int) ($d['min_purchase'] ?? 0) > 0) {
            $t[] = 'Minimal transaksi '.Money::idr($d['min_purchase']).'.';
        }
        if (! empty($d['max_discount'])) {
            $t[] = 'Potongan maksimal '.Money::idr($d['max_discount']).'.';
        }
        if (! empty($d['per_user_limit'])) {
            $t[] = 'Maksimal '.$d['per_user_limit'].' kali per akun.';
        }

        $t[] = 'Periode '.Carbon::parse($d['starts_at'])->translatedFormat('d F Y')
            .' sampai '.Carbon::parse($d['ends_at'])->translatedFormat('d F Y').'.';

        $t[] = ! empty($d['is_stackable'])
            ? 'Dapat digabung dengan promo lain yang juga dapat digabung.'
            : 'Tidak dapat digabung dengan promo lain.';

        return implode(' ', $t);
    }
}