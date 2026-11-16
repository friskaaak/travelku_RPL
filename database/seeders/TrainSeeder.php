<?php

namespace Database\Seeders;

use App\Models\Train;
use App\Models\TrainSchedule;
use App\Models\TrainSeat;
use App\Models\TrainStation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrainSeeder extends Seeder
{
    /**
     * [kotaA, kotaB,
     *  eksekutif [durasi menit, tarif, berangkat A ke B, berangkat B ke A],
     *  ekonomi   [durasi menit, tarif, berangkat A ke B, berangkat B ke A],
     *  tiket ekonomi ikut diskon?]
     */
    private const PAIRS = [
        ['Semarang', 'Tegal', [135, 160000, '07:30:00', '16:00:00'], [165, 85000, '10:15:00', '13:20:00'], true],
        ['Semarang', 'Bandung', [420, 420000, '08:00:00', '09:30:00'], [480, 255000, '19:00:00', '20:30:00'], true],
        ['Semarang', 'Jakarta', [330, 470000, '06:45:00', '15:30:00'], [400, 285000, '21:00:00', '22:15:00'], true],
        ['Tegal', 'Bandung', [250, 290000, '09:00:00', '14:00:00'], [300, 170000, '11:30:00', '17:45:00'], false],
        ['Tegal', 'Jakarta', [205, 310000, '10:00:00', '17:00:00'], [250, 180000, '05:30:00', '13:15:00'], true],
        ['Bandung', 'Jakarta', [180, 155000, '07:00:00', '18:30:00'], [215, 105000, '12:00:00', '20:45:00'], true],
    ];

    public function run(): void
    {
        $this->stations();
        $this->trainsAndSchedules();

        $this->command?->info(sprintf(
            'Kereta siap: %d stasiun, %d kereta, %d jadwal, %d kursi.',
            TrainStation::count(), Train::count(), TrainSchedule::count(), TrainSeat::count()
        ));
    }

    private function stations(): void
    {
        $rows = [
            ['SMT', 'Semarang Tawang', 'Semarang'],
            ['TG', 'Tegal', 'Tegal'],
            ['BD', 'Bandung', 'Bandung'],
            ['GMR', 'Gambir', 'Jakarta'],
            ['PSE', 'Pasar Senen', 'Jakarta'],
        ];

        foreach ($rows as [$code, $name, $city]) {
            TrainStation::firstOrCreate(['code' => $code], compact('name', 'city'));
        }
    }

    private function stationCode(string $city, string $key): string
    {
        return match ($city) {
            'Semarang' => 'SMT',
            'Tegal' => 'TG',
            'Bandung' => 'BD',
            'Jakarta' => $key === 'exec' ? 'GMR' : 'PSE', // eksekutif ke Gambir, ekonomi ke Pasar Senen
        };
    }

    private function trainsAndSchedules(): void
    {
        $stations = TrainStation::pluck('id', 'code');
        $days = (int) config('travel.seed_days');
        $today = now()->startOfDay();
        $seq = ['exec' => 0, 'eco' => 0];

        foreach (self::PAIRS as [$a, $b, $exec, $eco, $ecoDiscount]) {
            foreach (['exec' => $exec, 'eco' => $eco] as $key => [$duration, $fare, $depAB, $depBA]) {
                $class = $key === 'exec' ? 'Eksekutif' : 'Ekonomi';
                $eligible = $key === 'exec' || $ecoDiscount;

                foreach ([[$a, $b, $depAB], [$b, $a, $depBA]] as [$from, $to, $dep]) {
                    $seq[$key]++;
                    $number = sprintf('DM%d%02d', $key === 'exec' ? 1 : 2, $seq[$key]);

                    $train = Train::firstOrCreate(['number' => $number], [
                        'name' => "Demo {$class} {$from}-{$to}",
                        'is_demo' => true,
                    ]);

                    $originId = $stations[$this->stationCode($from, $key)];
                    $destId = $stations[$this->stationCode($to, $key)];

                    DB::transaction(function () use ($train, $originId, $destId, $dep, $duration, $fare, $class, $eligible, $days, $today) {
                        for ($i = 0; $i < $days; $i++) {
                            $date = $today->copy()->addDays($i);
                            $depAt = $date->copy()->setTimeFromTimeString($dep);

                            $schedule = TrainSchedule::firstOrCreate(
                                ['train_id' => $train->id, 'service_date' => $date->toDateString()],
                                [
                                    'origin_station_id' => $originId,
                                    'destination_station_id' => $destId,
                                    'departure_at' => $depAt,
                                    'arrival_at' => $depAt->copy()->addMinutes($duration),
                                    'duration_minutes' => $duration,
                                    'class' => $class,
                                    'base_fare' => $fare,
                                    'discount_eligible' => $eligible,
                                    'status' => 'scheduled',
                                ]
                            );

                            if (! $schedule->seats()->exists()) {
                                $this->seats($schedule);
                            }
                        }
                    });
                }
            }
        }
    }

    private function seats(TrainSchedule $schedule): void
    {
        $rowsCount = (int) config($schedule->class === 'Eksekutif' ? 'travel.train.exec_rows' : 'travel.train.eco_rows');
        $now = now();
        $rows = [];

        for ($r = 1; $r <= $rowsCount; $r++) {
            foreach (['A', 'B', 'C', 'D'] as $letter) {
                $rows[] = [
                    'train_schedule_id' => $schedule->id,
                    'seat_number' => $r.$letter,
                    'seat_class' => $schedule->class,
                    'price' => (int) $schedule->base_fare,
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        TrainSeat::insertOrIgnore($rows);
    }
}