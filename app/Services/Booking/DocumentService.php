<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Ticket;
use App\Support\Mask;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penerbitan receipt dan tiket. Semua isi dokumen disalin (snapshot) saat terbit,
 * sehingga perubahan harga, diskon, atau nama produk sesudahnya tidak mengubah dokumen.
 *
 * issue() dipanggil DI DALAM transaksi finalisasi. Idempoten: receipt dan tiket
 * yang sudah ada tidak dibuat ulang.
 */
class DocumentService
{
    public function issue(Booking $booking, Payment $payment): Receipt
    {
        $now = now();
        $booking->loadMissing('passengers', 'items');

        $receipt = Receipt::where('booking_id', $booking->id)->first();

        if (! $receipt) {
            $number = $this->uniqueValue('receipts', 'receipt_number', 'RCP-'.$now->format('ymd').'-', 8);

            $receipt = Receipt::create([
                'receipt_number' => $number,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'snapshot' => $this->receiptSnapshot($booking, $payment, $number, $now),
                'issued_at' => $now,
            ]);
        }

        if (! Ticket::where('booking_id', $booking->id)->exists()) {
            $this->issueTickets($booking, $payment, $now);
        }

        return $receipt;
    }

    /**
     * Pastikan pesanan terbayar punya receipt dan tiket (untuk pesanan yang dibuat sebelum Fase 8).
     * Mengembalikan null bila pesanan belum Confirmed atau tidak punya tagihan Paid.
     */
    public function ensureIssued(Booking $booking): ?Receipt
    {
        if ($booking->status !== Booking::CONFIRMED) {
            return null;
        }

        return DB::transaction(function () use ($booking) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->first();

            if (! $b || $b->status !== Booking::CONFIRMED) {
                return null;
            }

            $payment = $b->payments()->where('status', Payment::PAID)->latest('id')->first();

            return $payment ? $this->issue($b, $payment) : null;
        });
    }

    // ------------------------------------------------------------- RECEIPT

    private function receiptSnapshot(Booking $booking, Payment $payment, string $number, Carbon $now): array
    {
        $snap = $booking->snapshot ?? [];
        $pricing = $snap['pricing'] ?? [];
        $method = config('payment.methods.'.$payment->method_code, []);

        return [
            'brand' => config('app.name'),
            'receipt_number' => $number,
            'issued_at' => $now->toDateTimeString(),
            'booking' => [
                'code' => $booking->booking_code,
                'status' => Booking::CONFIRMED,
                'created_at' => $booking->created_at->toDateTimeString(),
                'confirmed_at' => ($booking->confirmed_at ?? $now)->toDateTimeString(),
            ],
            'customer' => [
                'name' => $booking->contact_name,
                'email' => $booking->contact_email,
                'phone' => $booking->contact_phone,
            ],
            'service_type' => $booking->service_type,
            'title' => $booking->title,
            'subtitle' => $booking->subtitle,
            'details' => $snap['details'] ?? [],
            'passengers' => $booking->passengers->sortBy('id')->map(fn ($p) => [
                'name' => $p->full_name,
                'type' => $p->passenger_type,
                'id_number' => Mask::id($p->id_number),
                'seat' => $p->seat_label,
                'room' => $p->room_label,
            ])->values()->all(),
            'items' => $booking->items->sortBy('id')->map(fn ($i) => [
                'description' => $i->description,
                'unit_price' => (int) $i->unit_price,
                'quantity' => (int) $i->quantity,
                'total_price' => (int) $i->total_price,
            ])->values()->all(),
            'pricing' => [
                'base' => (int) $booking->base_amount,
                'discounts' => $pricing['applied'] ?? [],
                'discount_total' => (int) $booking->discount_amount,
                'tax_percent' => (int) ($pricing['tax_percent'] ?? 0),
                'tax' => (int) $booking->tax_amount,
                'fee' => (int) $booking->fee_amount,
                'total' => (int) $booking->total_amount,
            ],
            'payment' => [
                'reference' => $payment->payment_reference,
                'invoice' => $payment->invoice_number,
                'method_code' => $payment->method_code,
                'method_label' => $method['label'] ?? $payment->method_code,
                'status' => 'Paid',
                'amount' => (int) $payment->amount,
                'paid_at' => ($payment->paid_at ?? $now)->toDateTimeString(),
                'is_simulated' => (bool) $payment->is_simulated,
                'provider' => $payment->provider,
            ],
        ];
    }

    // -------------------------------------------------------------- TIKET

    private function issueTickets(Booking $booking, Payment $payment, Carbon $now): void
    {
        $d = $booking->snapshot['details'] ?? [];

        $common = [
            'brand' => config('app.name'),
            'booking_code' => $booking->booking_code,
            'service_type' => $booking->service_type,
            'booked_at' => $booking->created_at->toDateTimeString(),
            'payment_status' => 'Paid',
            'is_simulated' => (bool) $payment->is_simulated,
            'issued_at' => $now->toDateTimeString(),
        ];

        if ($booking->service_type === 'hotel') {
            $this->makeTicket($booking, null, 'voucher', $now, $common + [
                'hotel' => Arr::only($d, [
                    'title', 'address', 'city', 'room_type', 'check_in', 'check_out', 'check_in_time',
                    'check_out_time', 'nights', 'room_qty', 'adults', 'children', 'free_cancellation', 'cancellation_policy',
                ]) + ['rooms' => collect($d['rooms'] ?? [])->pluck('room_number')->all()],
                'guests' => $booking->passengers->sortBy('id')->map(fn ($p) => [
                    'name' => $p->full_name,
                    'room' => $p->room_label,
                ])->values()->all(),
            ]);

            return;
        }

        $trip = $booking->service_type === 'flight'
            ? Arr::only($d, ['airline', 'flight_number', 'origin', 'destination', 'departure_at', 'arrival_at', 'duration_minutes', 'is_direct', 'baggage_kg', 'cabin_baggage_kg'])
            : Arr::only($d, ['train_name', 'train_number', 'class', 'origin', 'destination', 'departure_at', 'arrival_at', 'duration_minutes']);

        foreach ($booking->passengers->sortBy('id') as $p) {
            $seat = collect($d['seats'] ?? [])->firstWhere('number', $p->seat_label);

            $this->makeTicket($booking, $p->id, $booking->service_type, $now, $common + [
                'passenger' => [
                    'name' => $p->full_name,
                    'type' => $p->passenger_type,
                    'id_number' => Mask::id($p->id_number),
                ],
                'seat' => [
                    'number' => $p->seat_label,
                    'class' => $seat['cabin'] ?? ($seat['class'] ?? null),
                ],
                'trip' => $trip,
            ]);
        }
    }

    private function makeTicket(Booking $booking, ?int $passengerId, string $type, Carbon $now, array $snapshot): Ticket
    {
        $number = $this->uniqueValue('tickets', 'ticket_number', 'TKT-'.$now->format('ymd').'-', 8);

        do {
            $code = Str::random(40);
        } while (Ticket::where('verification_code', $code)->exists());

        return Ticket::create([
            'ticket_number' => $number,
            'booking_id' => $booking->id,
            'booking_passenger_id' => $passengerId,
            'ticket_type' => $type,
            'verification_code' => $code,
            'snapshot' => ['ticket_number' => $number] + $snapshot,
            'issued_at' => $now,
        ]);
    }

    /** Nomor acak tanpa karakter yang mudah tertukar (0, O, 1, I). Tidak berurutan, jadi tidak bisa ditebak. */
    private function uniqueValue(string $table, string $column, string $prefix, int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $value = $prefix.$code;
        } while (DB::table($table)->where($column, $value)->exists());

        return $value;
    }
}