<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    protected $guarded = [];

    public function flights() { return $this->hasMany(Flight::class); }
}