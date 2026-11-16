<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /** Pelanggan hanya boleh melihat pesanan, receipt, dan tiket miliknya sendiri. */
    public function view(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id;
    }
}