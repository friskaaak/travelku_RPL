<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
            'discount_eligible' => 'boolean',
        ];
    }

    public function train() { return $this->belongsTo(Train::class); }
    public function origin() { return $this->belongsTo(TrainStation::class, 'origin_station_id'); }
    public function destination() { return $this->belongsTo(TrainStation::class, 'destination_station_id'); }
    public function seats() { return $this->hasMany(TrainSeat::class); }
}