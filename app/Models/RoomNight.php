<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomNight extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['night' => 'date', 'expires_at' => 'datetime'];
    }

    public function room() { return $this->belongsTo(Room::class); }
    public function hold() { return $this->belongsTo(Hold::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
}