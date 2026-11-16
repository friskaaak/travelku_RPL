<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountRedemption extends Model
{
    protected $guarded = [];

    public function discount() { return $this->belongsTo(Discount::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function hold() { return $this->belongsTo(Hold::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
}