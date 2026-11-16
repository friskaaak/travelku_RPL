<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDelivery extends Model
{
    public const QUEUED = 'queued', SENDING = 'sending', SENT = 'sent', FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function receipt() { return $this->belongsTo(Receipt::class); }
    public function ticket() { return $this->belongsTo(Ticket::class); }
}