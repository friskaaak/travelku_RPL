<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightSeat extends Model
{
    public const AVAILABLE = 'available', HELD = 'held', CONFIRMED = 'confirmed';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['held_until' => 'datetime'];
    }

    public function schedule() { return $this->belongsTo(FlightSchedule::class, 'flight_schedule_id'); }
    public function hold() { return $this->belongsTo(Hold::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
}