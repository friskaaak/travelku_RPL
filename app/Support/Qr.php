<?php

namespace App\Support;

class Qr
{
    /** SVG kode QR, atau null bila paket QR tidak ada atau gagal. */
    public static function svg(string $text, int $size = 160): ?string
    {
        if (! class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            return null;
        }

        try {
            $svg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size($size)->margin(1)->generate($text);

            return $svg !== '' ? $svg : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Untuk disisipkan langsung di HTML (tanpa baris pembuka XML). */
    public static function inline(string $text, int $size = 160): ?string
    {
        $svg = self::svg($text, $size);

        return $svg ? preg_replace('/^<\?xml.*?\?>\s*/s', '', $svg) : null;
    }

    /** Untuk PDF: gambar dalam bentuk data URI. */
    public static function dataUri(string $text, int $size = 160): ?string
    {
        $svg = self::svg($text, $size);

        return $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }
}