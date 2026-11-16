<?php

namespace App\Policies;

use App\Models\Hold;
use App\Models\User;

class HoldPolicy
{
    /** Pelanggan hanya boleh melihat hold miliknya sendiri. */
    public function view(User $user, Hold $hold): bool
    {
        return $user->id === $hold->user_id;
    }

    /** Hanya pemilik, dan hanya saat hold masih aktif. */
    public function release(User $user, Hold $hold): bool
    {
        return $user->id === $hold->user_id && $hold->status === Hold::ACTIVE;
    }
}