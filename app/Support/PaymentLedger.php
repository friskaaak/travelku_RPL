<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\PaymentEvent;

class PaymentLedger
{
    /**
     * Catat satu kejadian pembayaran (jejak audit).
     * Bila $providerEventId diisi dan sudah pernah ada, database menolak (unique)
     * sehingga callback ganda tidak diproses dua kali.
     */
    public static function record(
        Payment $payment,
        string $type,
        ?string $from,
        ?string $to,
        string $source,
        array $payload = [],
        ?string $providerEventId = null
    ): PaymentEvent {
        return PaymentEvent::create([
            'payment_id' => $payment->id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'source' => $source,
            'provider_event_id' => $providerEventId,
            'payload' => $payload ?: null,
        ]);
    }
}