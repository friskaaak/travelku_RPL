<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const PENDING = 'Pending', PAID = 'Paid', FAILED = 'Failed', EXPIRED = 'Expired',
        CANCELLED = 'Cancelled', REFUNDED = 'Refunded', RECONCILIATION = 'Reconciliation Required';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'instructions' => 'array',
            'is_simulated' => 'boolean',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function events() { return $this->hasMany(PaymentEvent::class); }
    public function receipt() { return $this->hasOne(Receipt::class); }

    public function isPending(): bool { return $this->status === self::PENDING; }
    public function isPaid(): bool { return $this->status === self::PAID; }
}