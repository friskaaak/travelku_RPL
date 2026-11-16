<?php

namespace App\Support;

class Mask
{
    /** Budi Santoso menjadi B*** S******. Dipakai pada halaman verifikasi publik. */
    public static function name(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];

        return implode(' ', array_map(
            fn (string $w) => mb_substr($w, 0, 1).str_repeat('*', max(mb_strlen($w) - 1, 1)),
            array_filter($words, fn ($w) => $w !== '')
        ));
    }

    /** Hanya empat karakter terakhir yang terlihat. */
    public static function id(?string $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        $len = mb_strlen($id);

        return $len <= 4 ? str_repeat('*', $len) : str_repeat('*', $len - 4).mb_substr($id, -4);
    }
}