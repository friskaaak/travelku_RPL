<?php

namespace App\Services\Inventory;

use App\Exceptions\HoldException;
use App\Exceptions\PromoException;
use App\Models\DiscountRedemption;
use App\Models\FlightSchedule;
use App\Models\FlightSeat;
use App\Models\Hold;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomNight;
use App\Models\TrainSchedule;
use App\Models\TrainSeat;
use App\Models\User;
use App\Services\Discount\DiscountService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\PaymentLedger;

/**
 * STRATEGI PENGUNCIAN
 *  - Hotel : unique (room_id, night) di room_nights. Server mencoba INSERT satu baris per malam.
 *            Bila database menolak (duplikat), kamar itu dilewati. Tidak ada cek-lalu-tulis yang rawan balapan.
 *  - Kursi : baris dikunci lockForUpdate (urut id, mencegah deadlock), lalu status dicek ulang di dalam transaksi.
 *  - Semuanya dalam satu transaksi. Gagal satu langkah = batal semua (termasuk hold lama yang akan diganti).
 *  - Deadlock diulang otomatis maksimal 3 kali.
 *
 * KEDALUWARSA
 *  - expireDue() dipanggil secara lazy dan oleh penjadwal (holds:expire).
 *  - Waktu SERVER yang menentukan. Countdown browser hanya tampilan.
 */
class HoldService
{
    public function __construct(private DiscountService $discounts)
    {
    }

    // ------------------------------------------------------------------ HOTEL

    public function createHotelHold(
        User $user, Hotel $hotel, string $roomType, Carbon $checkIn, Carbon $checkOut,
        int $roomQty, int $adults, int $children, ?string $promo = null
    ): Hold {
        $nights = (int) abs($checkIn->diffInDays($checkOut));

        if ($nights < 1 || $nights > 30) {
            throw new HoldException('Lama menginap harus 1 sampai 30 malam.');
        }
        if ($checkIn->lt(now()->startOfDay())) {
            throw new HoldException('Tanggal check-in tidak boleh sebelum hari ini.');
        }
        if ($roomQty < 1 || $roomQty > 5) {
            throw new HoldException('Jumlah kamar harus 1 sampai 5.');
        }

        $perRoom = (int) ceil(($adults + $children) / $roomQty);

        $this->expireDue();

        $holdId = DB::transaction(function () use ($user, $hotel, $roomType, $checkIn, $checkOut, $roomQty, $adults, $children, $promo, $nights, $perRoom) {
            $this->releaseActiveOf($user, 'Diganti penahanan baru');
            $hold = $this->newHold($user, 'hotel', $roomQty);

            $candidates = Room::where('hotel_id', $hotel->id)
                ->where('room_type', $roomType)
                ->where('status', 'active')
                ->where('capacity', '>=', $perRoom)
                ->orderBy('room_number')
                ->get();

            if ($candidates->count() < $roomQty) {
                throw new HoldException('Tipe kamar ini tidak muat untuk jumlah tamu dan kamar yang Anda pilih.');
            }

            $dates = [];
            for ($d = $checkIn->copy(); $d->lt($checkOut); $d->addDay()) {
                $dates[] = $d->toDateString();
            }

            $claimed = collect();

            foreach ($candidates as $room) {
                if ($claimed->count() === $roomQty) {
                    break;
                }

                $rows = array_map(fn (string $night) => [
                    'room_id' => $room->id,
                    'night' => $night,
                    'hold_id' => $hold->id,
                    'booking_id' => null,
                    'status' => 'held',
                    'expires_at' => $hold->expires_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $dates);

                try {
                    // Satu pernyataan INSERT: berhasil semua malam atau tidak sama sekali.
                    RoomNight::insert($rows);
                    $claimed->push($room);
                } catch (QueryException $e) {
                    if (! $this->isDuplicateKey($e)) {
                        throw $e;
                    }
                    // Kamar ini sudah terpakai pada salah satu malam. Coba kamar berikutnya.
                }
            }

            if ($claimed->count() < $roomQty) {
                throw new HoldException('Kamar baru saja terpesan pelanggan lain untuk tanggal tersebut. Pilih tanggal atau tipe kamar lain.');
            }

            $base = (int) $claimed->sum('base_price') * count($dates);
            $this->price($hold, $user, 'hotel', $base, [], $promo);

            $hold->update(['details' => [
                'type' => 'hotel',
                'title' => $hotel->name,
                'subtitle' => "Kamar {$roomType}, {$nights} malam",
                'hotel_id' => $hotel->id,
                'hotel_slug' => $hotel->slug,
                'city' => $hotel->city,
                'address' => $hotel->address,
                'room_type' => $roomType,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'check_in_time' => substr($hotel->check_in_time, 0, 5),
                'check_out_time' => substr($hotel->check_out_time, 0, 5),
                'nights' => $nights,
                'room_qty' => $roomQty,
                'adults' => $adults,
                'children' => $children,
                'rooms' => $claimed->map(fn ($r) => [
                    'room_id' => $r->id,
                    'room_number' => $r->room_number,
                    'unit_price' => (int) $r->base_price,
                ])->values()->all(),
                'free_cancellation' => (bool) $hotel->free_cancellation,
                'cancellation_policy' => $hotel->cancellation_policy,
            ]]);

            return $hold->id;
        }, 3);

        return Hold::findOrFail($holdId);
    }

    // ----------------------------------------------------------------- PESAWAT

    public function createFlightHold(User $user, FlightSchedule $schedule, array $seatNumbers, ?string $promo = null): Hold
    {
        $schedule->loadMissing('flight.airline', 'flight.origin', 'flight.destination');
        $flight = $schedule->flight;

        if ($schedule->status !== 'scheduled' || ! $flight->is_published) {
            throw new HoldException('Jadwal penerbangan ini tidak tersedia.');
        }
        if ($schedule->departure_at->isPast()) {
            throw new HoldException('Penerbangan ini sudah berangkat.');
        }

        $seatNumbers = $this->cleanSeats($seatNumbers, 9);

        $this->expireDue();

        $holdId = DB::transaction(function () use ($user, $schedule, $flight, $seatNumbers, $promo) {
            $this->releaseActiveOf($user, 'Diganti penahanan baru');
            $hold = $this->newHold($user, 'flight', count($seatNumbers));

            $seats = FlightSeat::where('flight_schedule_id', $schedule->id)
                ->whereIn('seat_number', $seatNumbers)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->claimSeats($seats, $seatNumbers, $hold, FlightSeat::class);

            $base = (int) $seats->sum('price');
            $scope = $flight->is_international ? 'international' : 'domestic';
            $this->price($hold, $user, 'flight', $base, ['flight_scope' => $scope], $promo);

            $hold->update(['details' => [
                'type' => 'flight',
                'title' => $flight->airline->name.' '.$flight->flight_number,
                'subtitle' => $flight->origin->city.' ('.$flight->origin->iata.') ke '.$flight->destination->city.' ('.$flight->destination->iata.')',
                'schedule_id' => $schedule->id,
                'flight_number' => $flight->flight_number,
                'airline' => $flight->airline->name,
                'origin' => ['iata' => $flight->origin->iata, 'city' => $flight->origin->city, 'name' => $flight->origin->name],
                'destination' => ['iata' => $flight->destination->iata, 'city' => $flight->destination->city, 'name' => $flight->destination->name],
                'departure_at' => $schedule->departure_at->toDateTimeString(),
                'arrival_at' => $schedule->arrival_at->toDateTimeString(),
                'duration_minutes' => (int) $flight->duration_minutes,
                'is_direct' => (bool) $flight->is_direct,
                'is_international' => (bool) $flight->is_international,
                'baggage_kg' => (int) $flight->baggage_kg,
                'cabin_baggage_kg' => (int) $flight->cabin_baggage_kg,
                'seats' => $seats->sortBy('id')->map(fn ($s) => [
                    'seat_id' => $s->id,
                    'number' => $s->seat_number,
                    'cabin' => $s->cabin_class,
                    'price' => (int) $s->price,
                ])->values()->all(),
            ]]);

            return $hold->id;
        }, 3);

        return Hold::findOrFail($holdId);
    }

    // ------------------------------------------------------------------ KERETA

    public function createTrainHold(User $user, TrainSchedule $schedule, array $seatNumbers, ?string $promo = null): Hold
    {
        $schedule->loadMissing('train', 'origin', 'destination');

        if ($schedule->status !== 'scheduled') {
            throw new HoldException('Jadwal kereta ini tidak tersedia.');
        }
        if ($schedule->departure_at->isPast()) {
            throw new HoldException('Kereta ini sudah berangkat.');
        }

        $seatNumbers = $this->cleanSeats($seatNumbers, 4);

        $this->expireDue();

        $holdId = DB::transaction(function () use ($user, $schedule, $seatNumbers, $promo) {
            $this->releaseActiveOf($user, 'Diganti penahanan baru');
            $hold = $this->newHold($user, 'train', count($seatNumbers));

            $seats = TrainSeat::where('train_schedule_id', $schedule->id)
                ->whereIn('seat_number', $seatNumbers)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->claimSeats($seats, $seatNumbers, $hold, TrainSeat::class);

            $base = (int) $seats->sum('price');
            $this->price($hold, $user, 'train', $base, [], $promo, (bool) $schedule->discount_eligible);

            $hold->update(['details' => [
                'type' => 'train',
                'title' => $schedule->train->name,
                'subtitle' => $schedule->origin->city.' ke '.$schedule->destination->city.', '.$schedule->class,
                'schedule_id' => $schedule->id,
                'train_number' => $schedule->train->number,
                'train_name' => $schedule->train->name,
                'class' => $schedule->class,
                'origin' => ['code' => $schedule->origin->code, 'city' => $schedule->origin->city, 'name' => $schedule->origin->name],
                'destination' => ['code' => $schedule->destination->code, 'city' => $schedule->destination->city, 'name' => $schedule->destination->name],
                'departure_at' => $schedule->departure_at->toDateTimeString(),
                'arrival_at' => $schedule->arrival_at->toDateTimeString(),
                'duration_minutes' => (int) $schedule->duration_minutes,
                'seats' => $seats->sortBy('id')->map(fn ($s) => [
                    'seat_id' => $s->id,
                    'number' => $s->seat_number,
                    'class' => $s->seat_class,
                    'price' => (int) $s->price,
                ])->values()->all(),
            ]]);

            return $hold->id;
        }, 3);

        return Hold::findOrFail($holdId);
    }

    // -------------------------------------------------------- LEPAS / KEDALUWARSA

    /**
     * Lepas hold yang masih aktif: inventori dikembalikan dan kuota diskon dilepas.
     * Aman dipanggil berulang. Mengembalikan true hanya bila benar-benar melepas.
     */
       /**
     * Lepas hold yang masih aktif: inventori dikembalikan dan kuota diskon dilepas.
     * Pesanan yang masih menunggu bayar ikut ditutup. Aman dipanggil berulang.
     * Mengembalikan true hanya bila benar-benar melepas.
     */
    public function release(Hold $hold, string $status = Hold::RELEASED, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($hold, $status, $reason) {
            $locked = Hold::whereKey($hold->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== Hold::ACTIVE) {
                return false; // sudah dilepas, kedaluwarsa, atau terpakai
            }

            // Hanya baris 'held'. Baris 'confirmed' milik booking tidak disentuh.
            RoomNight::where('hold_id', $locked->id)->where('status', 'held')->delete();

            foreach ([FlightSeat::class, TrainSeat::class] as $model) {
                $model::where('hold_id', $locked->id)->where('status', 'held')
                    ->update(['status' => 'available', 'hold_id' => null, 'held_until' => null]);
            }

            DiscountRedemption::where('hold_id', $locked->id)->where('status', 'reserved')->get()
                ->each(fn ($r) => $this->discounts->release($r));

            $locked->update([
                'status' => $status,
                'released_at' => now(),
                'release_reason' => $reason,
            ]);

            $this->closePendingBooking($locked, $status === Hold::EXPIRED, $reason);

            return true;
        });
    }

    /** Booking Pending milik hold ini ditutup beserta tagihan Pending-nya. */
    private function closePendingBooking(Hold $hold, bool $expired, ?string $reason): void
    {
        $booking = Booking::where('hold_id', $hold->id)->lockForUpdate()->first();

        if (! $booking || $booking->status !== Booking::PENDING) {
            return;
        }

        $booking->update([
            'status' => $expired ? Booking::EXPIRED : Booking::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $newStatus = $expired ? Payment::EXPIRED : Payment::CANCELLED;

        Payment::where('booking_id', $booking->id)->where('status', Payment::PENDING)
            ->lockForUpdate()->get()->each(function (Payment $p) use ($newStatus, $reason) {
                $p->update(['status' => $newStatus, 'failed_at' => now(), 'failure_reason' => $reason]);
                PaymentLedger::record($p, $newStatus === Payment::EXPIRED ? 'payment_expired' : 'payment_cancelled', Payment::PENDING, $newStatus, 'system', ['reason' => $reason]);
            });
    }

    /**
     * Atur ulang batas waktu hold (dan inventori yang tertahan). Dipakai saat checkout
     * agar kamar atau kursi tetap tertahan sampai batas bayar.
     */
    public function extendTo(Hold $hold, Carbon $until): void
    {
        DB::transaction(function () use ($hold, $until) {
            $locked = Hold::whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Hold::ACTIVE) {
                throw new HoldException('Penahanan sudah tidak aktif.');
            }

            $locked->update(['expires_at' => $until]);

            RoomNight::where('hold_id', $locked->id)->where('status', 'held')->update(['expires_at' => $until]);

            foreach ([FlightSeat::class, TrainSeat::class] as $model) {
                $model::where('hold_id', $locked->id)->where('status', 'held')->update(['held_until' => $until]);
            }
        });
    }

    /** Lepas semua hold yang waktunya habis, lalu bersihkan baris sisa tanpa hold. */
    public function expireDue(): int
    {
        $count = 0;

        Hold::where('status', Hold::ACTIVE)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->each(function (Hold $hold) use (&$count) {
                if ($this->release($hold, Hold::EXPIRED, 'Waktu penahanan habis')) {
                    $count++;
                }
            });

        // Baris yatim (hold sudah tidak ada) yang waktu tahannya lewat.
        RoomNight::where('status', 'held')->where('expires_at', '<=', now())->delete();

        foreach ([FlightSeat::class, TrainSeat::class] as $model) {
            $model::where('status', 'held')->where('held_until', '<=', now())
                ->update(['status' => 'available', 'hold_id' => null, 'held_until' => null]);
        }

        return $count;
    }

    /** Kedaluwarsakan satu hold bila waktunya sudah lewat, lalu kembalikan data terbarunya. */
    public function expireIfDue(Hold $hold): Hold
    {
        if ($hold->status === Hold::ACTIVE && $hold->expires_at->lte(now())) {
            $this->release($hold, Hold::EXPIRED, 'Waktu penahanan habis');
        }

        return $hold->fresh();
    }

    public function activeFor(User $user): ?Hold
    {
        return Hold::where('user_id', $user->id)
            ->where('status', Hold::ACTIVE)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    // ----------------------------------------------------------------- INTERNAL

    private function releaseActiveOf(User $user, string $reason): void
    {
        Hold::where('user_id', $user->id)->where('status', Hold::ACTIVE)->orderBy('id')->get()
            ->each(fn (Hold $old) => $this->release($old, Hold::RELEASED, $reason));
    }

    private function newHold(User $user, string $service, int $quantity): Hold
    {
        return Hold::create([
            'hold_code' => $this->uniqueCode(),
            'user_id' => $user->id,
            'service_type' => $service,
            'status' => Hold::ACTIVE,
            'details' => [],
            'quantity' => $quantity,
            'base_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'expires_at' => now()->addMinutes((int) config('payment.hold_expiry_minutes', 15)),
        ]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'HLD-'.strtoupper(Str::random(10));
        } while (Hold::where('hold_code', $code)->exists());

        return $code;
    }

    private function cleanSeats(array $seats, int $max): array
    {
        $seats = array_values(array_unique(array_map(fn ($s) => strtoupper(trim((string) $s)), $seats)));

        if ($seats === [] || count($seats) > $max) {
            throw new HoldException("Pilih 1 sampai {$max} kursi.");
        }

        foreach ($seats as $s) {
            if (! preg_match('/^\d{1,2}[A-F]$/', $s)) {
                throw new HoldException('Nomor kursi tidak valid.');
            }
        }

        return $seats;
    }

    /** Kursi bebas = available, atau held yang waktu tahannya sudah lewat. */
    private function seatIsFree($seat): bool
    {
        return $seat->status === 'available'
            || ($seat->status === 'held' && $seat->held_until && $seat->held_until->lte(now()));
    }

    /** Dipanggil di dalam transaksi, setelah baris dikunci. */
    private function claimSeats($seats, array $requested, Hold $hold, string $modelClass): void
    {
        if ($seats->count() !== count($requested)) {
            throw new HoldException('Ada kursi yang tidak ditemukan pada jadwal ini.');
        }

        $taken = $seats->reject(fn ($s) => $this->seatIsFree($s))->pluck('seat_number');

        if ($taken->isNotEmpty()) {
            throw new HoldException('Kursi '.$taken->sort()->implode(', ').' baru saja diambil pelanggan lain. Pilih kursi lain.');
        }

        $modelClass::whereIn('id', $seats->pluck('id'))->update([
            'status' => 'held',
            'hold_id' => $hold->id,
            'booking_id' => null,
            'held_until' => $hold->expires_at,
        ]);
    }

    /** Hitung harga di server, ambil kuota promo, dan simpan snapshot ke hold. */
    private function price(Hold $hold, User $user, string $service, int $base, array $context, ?string $promo, bool $eligible = true): void
    {
        $promo = $promo ? $this->discounts->normalizeCode($promo) : null;

        if (! $eligible) {
            if ($promo) {
                throw new HoldException('Tiket ini tidak termasuk program promo.');
            }
            $quote = ['applied' => [], 'discount_total' => 0, 'promo_code' => null, 'promo_discount' => null];
        } else {
            $quote = $this->discounts->quote($service, $base, $context, $promo, $user);

            if ($promo && $quote['promo_error']) {
                throw new HoldException('Kode promo tidak dapat dipakai: '.$quote['promo_error']);
            }
        }

        $discountTotal = (int) $quote['discount_total'];
        $taxPercent = (int) config("travel.tax_percent.{$service}", 0);
        $tax = intdiv(($base - $discountTotal) * $taxPercent, 100);
        $total = $base - $discountTotal + $tax;

        $promoDiscount = $quote['promo_discount'];
        if ($promoDiscount) {
            $row = collect($quote['applied'])->first(fn ($a) => $a['discount']->id === $promoDiscount->id);
            $promoAmount = (int) ($row['amount'] ?? 0);

            try {
                $this->discounts->reserve($promoDiscount, $user, $promoAmount, $hold->id);
            } catch (PromoException $e) {
                throw new HoldException($e->getMessage());
            }
        }

        $applied = array_map(fn ($a) => [
            'discount_id' => $a['discount']->id,
            'name' => $a['discount']->name,
            'label' => $a['discount']->label,
            'code' => $a['discount']->code,
            'type' => $a['discount']->type,
            'value' => (int) $a['discount']->value,
            'amount' => (int) $a['amount'],
        ], $quote['applied']);

        $hold->update([
            'base_amount' => $base,
            'discount_amount' => $discountTotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'discount_id' => $applied[0]['discount_id'] ?? null,
            'promo_code' => $quote['promo_code'],
            'pricing_snapshot' => [
                'base' => $base,
                'discount_total' => $discountTotal,
                'tax_percent' => $taxPercent,
                'tax' => $tax,
                'total' => $total,
                'applied' => $applied,
                'calculated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}