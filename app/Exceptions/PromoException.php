<?php

namespace App\Exceptions;

use RuntimeException;

/** Dilempar saat kode promo gagal divalidasi atau kuotanya habis. Pesannya aman ditampilkan ke pengguna. */
class PromoException extends RuntimeException
{
}