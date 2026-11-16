<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'amenities' => 'array',
            'free_cancellation' => 'boolean',
            'is_published' => 'boolean',
            'rating' => 'decimal:1',
        ];
    }

    public function rooms() { return $this->hasMany(Room::class); }
}