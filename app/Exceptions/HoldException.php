<?php

namespace App\Exceptions;

use RuntimeException;

/** Dilempar saat hold gagal dibuat. Pesannya aman ditampilkan ke pengguna. */
class HoldException extends RuntimeException
{
}