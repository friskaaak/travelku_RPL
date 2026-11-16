<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hold extends Model
{
    public const ACTIVE = 'Active', EXPIRED = 'Expired', CONSUMED = 'Consumed', RELEASED = 'Released';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'pricing_snapshot' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function discount() { return $this->belongsTo(Discount::class); }
    public function booking() { return $this->hasOne(Booking::class); }
    public function roomNights() { return $this->hasMany(RoomNight::class); }
    public function flightSeats() { return $this->hasMany(FlightSeat::class); }
    public function trainSeats() { return $this->hasMany(TrainSeat::class); }

    /** Pengecekan server. Countdown di browser hanya tampilan. */
    public function isUsable(): bool
    {
        return $this->status === self::ACTIVE && $this->expires_at->isFuture();
    }
}