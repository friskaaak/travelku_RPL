<?php

namespace App\Services\Inventory;

use Illuminate\Support\Collection;

class SeatMapBuilder
{
    /**
     * Susun peta kursi dari baris database. Status ditentukan SERVER.
     * $classKey: 'cabin_class' (pesawat) atau 'seat_class' (kereta).
     * Kursi harus dimuat bersama relasi hold (with('hold:id,user_id')).
     *
     * @return array{rows: array, counts: array<string,int>}
     */
    public function build(Collection $seats, ?int $userId, string $classKey): array
    {
        $counts = ['available' => 0, 'held' => 0, 'confirmed' => 0, 'mine' => 0];
        $rows = [];

        foreach ($seats as $seat) {
            if (! preg_match('/^(\d+)([A-Z])$/', $seat->seat_number, $m)) {
                continue;
            }

            $state = $this->state($seat, $userId);
            $counts[$state]++;

            $row = (int) $m[1];
            $rows[$row]['row'] = $row;
            $rows[$row]['class'] = $seat->{$classKey};
            $rows[$row]['seats'][] = [
                'label' => $seat->seat_number,
                'letter' => $m[2],
                'state' => $state,
                'price' => (int) $seat->price,
                'class' => $seat->{$classKey},
            ];
        }

        ksort($rows);
        foreach ($rows as &$r) {
            usort($r['seats'], fn ($a, $b) => strcmp($a['letter'], $b['letter']));
        }
        unset($r);

        return ['rows' => array_values($rows), 'counts' => $counts];
    }

    public function state($seat, ?int $userId): string
    {
        if ($seat->status === 'confirmed') {
            return 'confirmed';
        }

        if ($seat->status === 'held' && $seat->held_until && $seat->held_until->isFuture()) {
            return ($userId && $seat->hold && (int) $seat->hold->user_id === $userId) ? 'mine' : 'held';
        }

        // 'available', atau 'held' yang sudah kedaluwarsa
        return 'available';
    }
}