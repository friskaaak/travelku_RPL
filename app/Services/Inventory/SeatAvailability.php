<?php

namespace App\Services\Inventory;

use App\Models\FlightSeat;
use App\Models\TrainSeat;

class SeatAvailability
{
    /**
     * Jumlah kursi pesawat KOSONG per jadwal dan kelas kabin.
     * Kosong = 'available', atau 'held' yang waktu tahannya sudah lewat (kedaluwarsa lazy).
     *
     * @return array<int, array<string,int>>  [id_jadwal => ['Economy' => 40, 'Business' => 5]]
     */
    public function flightCounts(iterable $scheduleIds): array
    {
        $ids = collect($scheduleIds)->values()->all();
        if (! $ids) {
            return [];
        }

        $rows = FlightSeat::query()
            ->selectRaw('flight_schedule_id, cabin_class, COUNT(*) AS total')
            ->whereIn('flight_schedule_id', $ids)
            ->where(fn ($q) => $this->free($q))
            ->groupBy('flight_schedule_id', 'cabin_class')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->flight_schedule_id][$r->cabin_class] = (int) $r->total;
        }

        return $out;
    }

    /** @return array<int,int>  [id_jadwal => jumlah kursi kosong] */
    public function trainCounts(iterable $scheduleIds): array
    {
        $ids = collect($scheduleIds)->values()->all();
        if (! $ids) {
            return [];
        }

        return TrainSeat::query()
            ->selectRaw('train_schedule_id, COUNT(*) AS total')
            ->whereIn('train_schedule_id', $ids)
            ->where(fn ($q) => $this->free($q))
            ->groupBy('train_schedule_id')
            ->pluck('total', 'train_schedule_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function free($q): void
    {
        $q->where('status', 'available')
            ->orWhere(function ($held) {
                $held->where('status', 'held')->where('held_until', '<=', now());
            });
    }
}