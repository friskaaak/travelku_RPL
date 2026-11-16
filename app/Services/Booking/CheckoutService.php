<?php

namespace App\Services\Booking;

use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPassenger;
use App\Models\Hold;
use App\Models\Payment;
use App\Models\User;
use App\Services\Inventory\HoldService;
use App\Services\Payment\PaymentService;
use App\Support\PaymentMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(private PaymentService $payments, private HoldService $holds)
    {
    }

    /**
     * Ubah hold menjadi booking Pending + tagihan Pending. Idempoten: hold yang sama
     * tidak pernah menghasilkan dua booking (bookings.hold_id unik).
     *
     * @param array{name:string, phone:string} $contact
     * @param array<int, array{name:string, type?:string, id_number?:?string}> $passengers
     */
    public function create(Hold $hold, User $user, array $contact, array $passengers, string $methodCode): Payment
    {
        if (! PaymentMode::method($methodCode)) {
            throw new PaymentException('Metode pembayaran tidak valid.');
        }

        $this->holds->expireDue();

        return DB::transaction(function () use ($hold, $user, $contact, $passengers, $methodCode) {
            $h = Hold::whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($h->user_id !== $user->id) {
                throw new PaymentException('Penahanan ini bukan milik Anda.');
            }

            // Idempoten: pesanan untuk hold ini sudah ada, kembalikan tagihannya.
            $existing = Booking::where('hold_id', $h->id)->first();
            if ($existing && ($payment = $existing->payments()->latest('id')->first())) {
                return $payment;
            }

            if (! $h->isUsable()) {
                throw new PaymentException('Penahanan sudah tidak aktif atau waktunya habis. Silakan pilih ulang dari halaman produk.');
            }
            if (count($passengers) !== (int) $h->quantity) {
                throw new PaymentException('Jumlah data tamu atau penumpang tidak sesuai dengan pesanan.');
            }

            // Inventori tetap tertahan sampai batas bayar.
            $expires = now()->addMinutes((int) config('payment.expiry_minutes', 60));
            $this->holds->extendTo($h, $expires);
            $h->refresh();

            $d = $h->details;
            $isHotel = $h->service_type === 'hotel';

            $booking = Booking::create([
                'booking_code' => $this->uniqueCode(),
                'user_id' => $user->id,
                'hold_id' => $h->id,
                'service_type' => $h->service_type,
                'status' => Booking::PENDING,
                'title' => $d['title'],
                'subtitle' => $d['subtitle'] ?? null,
                'start_date' => $isHotel ? $d['check_in'] : Carbon::parse($d['departure_at'])->toDateString(),
                'end_date' => $isHotel ? $d['check_out'] : Carbon::parse($d['arrival_at'])->toDateString(),
                'contact_name' => trim($contact['name']),
                'contact_email' => $user->email,
                'contact_phone' => trim($contact['phone']),
                'base_amount' => $h->base_amount,
                'discount_amount' => $h->discount_amount,
                'tax_amount' => $h->tax_amount,
                'fee_amount' => 0,
                'total_amount' => $h->total_amount,
                'discount_id' => $h->discount_id,
                'promo_code' => $h->promo_code,
                // Salinan tetap: perubahan harga atau diskon oleh admin tidak mengubah pesanan ini.
                'snapshot' => [
                    'hold_code' => $h->hold_code,
                    'details' => $d,
                    'pricing' => $h->pricing_snapshot,
                ],
            ]);

            $this->createItems($booking, $h);
            $this->createPassengers($booking, $h, $passengers);

            return $this->payments->makePayment($booking, $h, $methodCode, 'chk:'.$h->hold_code, $expires);
        }, 3);
    }

    private function createItems(Booking $booking, Hold $hold): void
    {
        $d = $hold->details;

        if ($hold->service_type === 'hotel') {
            foreach ($d['rooms'] as $room) {
                $nights = (int) $d['nights'];
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'item_type' => 'room',
                    'item_id' => $room['room_id'],
                    'description' => "Kamar {$room['room_number']} ({$d['room_type']}), {$nights} malam",
                    'unit_price' => (int) $room['unit_price'],
                    'quantity' => $nights,
                    'total_price' => (int) $room['unit_price'] * $nights,
                    'snapshot' => $room,
                ]);
            }

            return;
        }

        $type = $hold->service_type === 'flight' ? 'flight_seat' : 'train_seat';
        foreach ($d['seats'] as $seat) {
            BookingItem::create([
                'booking_id' => $booking->id,
                'item_type' => $type,
                'item_id' => $seat['seat_id'],
                'description' => 'Kursi '.$seat['number'].', '.($seat['cabin'] ?? $seat['class']),
                'unit_price' => (int) $seat['price'],
                'quantity' => 1,
                'total_price' => (int) $seat['price'],
                'snapshot' => $seat,
            ]);
        }
    }

    private function createPassengers(Booking $booking, Hold $hold, array $passengers): void
    {
        $d = $hold->details;
        $isHotel = $hold->service_type === 'hotel';

        foreach (array_values($passengers) as $i => $p) {
            BookingPassenger::create([
                'booking_id' => $booking->id,
                'full_name' => trim($p['name']),
                'passenger_type' => $isHotel ? 'adult' : ($p['type'] ?? 'adult'),
                'id_number' => $isHotel ? null : ($p['id_number'] ?? null),
                'seat_label' => $isHotel ? null : ($d['seats'][$i]['number'] ?? null),
                'room_label' => $isHotel ? ($d['rooms'][$i]['room_number'] ?? null) : null,
            ]);
        }
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'TKB-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }
}