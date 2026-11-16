<?php

namespace App\Services\Inventory;

use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class HotelAvailability
{
    /**
     * Kamar fisik yang BEBAS untuk seluruh rentang menginap.
     * Malam check-out tidak dihitung (check-in 20, check-out 22 = malam 20 dan 21).
     *
     * Kamar dianggap terpakai bila pada salah satu malam ada:
     *  - baris 'confirmed', atau
     *  - baris 'held' yang BELUM kedaluwarsa (hold kedaluwarsa diabaikan).
     */
    public function availableRoomsQuery(Carbon $checkIn, Carbon $checkOut, int $minCapacity = 1): Builder
    {
        $firstNight = $checkIn->toDateString();
        $lastNight = $checkOut->copy()->subDay()->toDateString();

        return Room::query()
            ->where('status', 'active')
            ->where('capacity', '>=', $minCapacity)
            ->whereNotExists(function ($q) use ($firstNight, $lastNight) {
                $q->selectRaw('1')
                    ->from('room_nights')
                    ->whereColumn('room_nights.room_id', 'rooms.id')
                    ->whereBetween('room_nights.night', [$firstNight, $lastNight])
                    ->where(function ($w) {
                        $w->where('room_nights.status', 'confirmed')
                            ->orWhere(function ($h) {
                                $h->where('room_nights.status', 'held')
                                    ->where('room_nights.expires_at', '>', now());
                            });
                    });
            });
    }
}