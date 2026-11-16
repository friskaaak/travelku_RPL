<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
        ];
    }

    public function flight() { return $this->belongsTo(Flight::class); }
    public function seats() { return $this->hasMany(FlightSeat::class); }
}