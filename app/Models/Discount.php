<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'scope_filter' => 'array',
            'is_automatic' => 'boolean',
            'is_stackable' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function campaigns() { return $this->belongsToMany(Campaign::class, 'campaign_discount'); }
    public function redemptions() { return $this->hasMany(DiscountRedemption::class); }

    /** Aktif menurut waktu SERVER, bukan waktu browser. */
    public function scopeActiveNow($q)
    {
        return $q->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /** inactive | scheduled | ended | exhausted | active (waktu server). */
    public function status(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }
        if ($this->starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($this->ends_at->isPast()) {
            return 'ended';
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'exhausted';
        }

        return 'active';
    }
}