<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    /** Nama provider yang disimpan di payments.provider. */
    public function name(): string;

    /** True bila ini simulasi (tidak ada uang sungguhan). */
    public function isSimulation(): bool;

    /**
     * Buat instruksi pembayaran untuk satu tagihan.
     *
     * @return array{provider_reference: ?string, payment_code: ?string, qr_payload: ?string, instructions: array}
     */
    public function createCharge(Payment $payment, array $method): array;

    /**
     * Verifikasi dan baca webhook. Lempar PaymentException bila tidak sah.
     *
     * @return array{order_id: string, status: string, amount: int, event_id: string, payload: array}
     *         status: paid | pending | failed | expired
     */
    public function parseWebhook(Request $request): array;
}