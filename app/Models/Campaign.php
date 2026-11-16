<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'is_published' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function discounts() { return $this->belongsToMany(Discount::class, 'campaign_discount'); }

    public function scopeActiveNow($q)
    {
        return $q->where('is_published', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeUpcoming($q)
    {
        return $q->where('is_published', true)->where('starts_at', '>', now());
    }

    /** draft | upcoming | ended | active, berdasarkan waktu SERVER. */
    public function status(): string
    {
        if (! $this->is_published) {
            return 'draft';
        }
        if ($this->starts_at->isFuture()) {
            return 'upcoming';
        }
        if ($this->ends_at->isPast()) {
            return 'ended';
        }

        return 'active';
    }

    public function isActiveNow(): bool
    {
        return $this->status() === 'active';
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_image ? asset('storage/'.$this->banner_image) : null;
    }

    /** Gambar unggahan (dengan lapisan gelap agar teks terbaca) atau gradien bawaan. */
    public function bannerStyle(): string
    {
        if ($url = $this->bannerUrl()) {
            return "background:linear-gradient(rgba(16,24,40,.25),rgba(16,24,40,.75)),url('{$url}') center/cover no-repeat";
        }

        $palette = [['#155EEF', '#7A5AF8'], ['#067647', '#47CD89'], ['#B54708', '#FDB022'], ['#1247B8', '#53B1FD'], ['#7A5AF8', '#B692F6']];
        [$a, $b] = $palette[abs(crc32((string) $this->slug)) % count($palette)];

        return "background:linear-gradient(135deg,{$a},{$b})";
    }
}