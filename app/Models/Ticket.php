<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'issued_at' => 'datetime'];
    }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function passenger() { return $this->belongsTo(BookingPassenger::class, 'booking_passenger_id'); }
}