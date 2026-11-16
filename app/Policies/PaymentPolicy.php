<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /** Pelanggan hanya boleh mengakses tagihan miliknya sendiri. */
    public function view(User $user, Payment $payment): bool
    {
        return $user->id === $payment->user_id;
    }
}