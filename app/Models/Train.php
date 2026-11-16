<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Train extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean'];
    }

    public function schedules() { return $this->hasMany(TrainSchedule::class); }
}