<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    public const PENDING = 'Pending', CONFIRMED = 'Confirmed', CANCELLED = 'Cancelled', EXPIRED = 'Expired';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function hold() { return $this->belongsTo(Hold::class); }
    public function items() { return $this->hasMany(BookingItem::class); }
    public function passengers() { return $this->hasMany(BookingPassenger::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function receipt() { return $this->hasOne(Receipt::class); }
    public function tickets() { return $this->hasMany(Ticket::class); }
    public function emailDeliveries() { return $this->hasMany(EmailDelivery::class); }
}