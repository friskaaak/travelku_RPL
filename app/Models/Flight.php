<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_direct' => 'boolean',
            'is_international' => 'boolean',
            'is_demo' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function airline() { return $this->belongsTo(Airline::class); }
    public function origin() { return $this->belongsTo(Airport::class, 'origin_airport_id'); }
    public function destination() { return $this->belongsTo(Airport::class, 'destination_airport_id'); }
    public function schedules() { return $this->hasMany(FlightSchedule::class); }
}