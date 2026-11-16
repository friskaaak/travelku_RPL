<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightSchedule;
use App\Models\FlightSeat;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FlightSeeder extends Seeder
{
    public function run(): void
    {
        $this->airports();
        $this->airlines();
        $this->flights();
        $this->schedules();

        $this->command?->info(sprintf(
            'Pesawat siap: %d bandara, %d maskapai, %d penerbangan, %d jadwal, %d kursi.',
            Airport::count(), Airline::count(), Flight::count(), FlightSchedule::count(), FlightSeat::count()
        ));
    }

    private function airports(): void
    {
        $rows = [
            ['SRG', 'Ahmad Yani International Airport', 'Semarang', 'Indonesia'],
            ['CGK', 'Soekarno-Hatta International Airport', 'Jakarta', 'Indonesia'],
            ['DPS', 'I Gusti Ngurah Rai International Airport', 'Bali', 'Indonesia'],
            ['HND', 'Haneda Airport', 'Tokyo', 'Jepang'],
            ['ICN', 'Incheon International Airport', 'Seoul', 'Korea Selatan'],
            ['SYD', 'Sydney Kingsford Smith Airport', 'Sydney', 'Australia'],
        ];

        foreach ($rows as [$iata, $name, $city, $country]) {
            Airport::firstOrCreate(['iata' => $iata], compact('name', 'city', 'country'));
        }
    }

    private function airlines(): void
    {
        // Maskapai FIKTIF untuk demonstrasi.
        foreach ([['NSA', 'Nusantara Air Demo'], ['SMA', 'Samudra Air Demo'], ['GPA', 'Garda Pasifik Demo']] as [$code, $name]) {
            Airline::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    private function flights(): void
    {
        $airports = Airport::pluck('id', 'iata');
        $airlines = Airline::pluck('id', 'code');

        $transit = 'Transit di Jakarta (CGK) sekitar 2 jam. Itinerari demonstrasi, bukan layanan nyata.';
        $extra = 'Rute tambahan demonstrasi. Bukan jadwal layanan nyata.';

        // [nomor, maskapai, asal, tujuan, berangkat, durasi menit, langsung?, catatan,
        //  internasional?, bagasi kg, harga ekonomi, harga bisnis (null = tidak ada)]
        $rows = [
            // ---- Domestik (dua arah) ----
            ['NS101', 'NSA', 'SRG', 'CGK', '06:30:00', 75, true, null, false, 20, 780000, 1650000],
            ['NS102', 'NSA', 'CGK', 'SRG', '08:30:00', 75, true, null, false, 20, 780000, 1650000],
            ['SM201', 'SMA', 'SRG', 'CGK', '15:10:00', 75, true, null, false, 20, 720000, null],
            ['SM202', 'SMA', 'CGK', 'SRG', '17:20:00', 75, true, null, false, 20, 720000, null],
            ['NS111', 'NSA', 'SRG', 'DPS', '07:00:00', 100, true, null, false, 20, 1050000, 2200000],
            ['NS112', 'NSA', 'DPS', 'SRG', '10:00:00', 100, true, null, false, 20, 1050000, 2200000],
            ['SM211', 'SMA', 'SRG', 'DPS', '13:45:00', 100, true, null, false, 20, 980000, null],
            ['SM212', 'SMA', 'DPS', 'SRG', '16:30:00', 100, true, null, false, 20, 980000, null],
            ['NS121', 'NSA', 'CGK', 'DPS', '07:30:00', 115, true, null, false, 20, 1150000, 2400000],
            ['NS122', 'NSA', 'DPS', 'CGK', '10:45:00', 115, true, null, false, 20, 1150000, 2400000],
            ['SM221', 'SMA', 'CGK', 'DPS', '14:00:00', 115, true, null, false, 20, 1050000, null],
            ['SM222', 'SMA', 'DPS', 'CGK', '17:10:00', 115, true, null, false, 20, 1050000, null],

            // ---- Internasional dari/ke Jakarta (dua arah) ----
            ['GP301', 'GPA', 'CGK', 'HND', '22:30:00', 435, true, null, true, 30, 6200000, 17500000],
            ['GP302', 'GPA', 'HND', 'CGK', '11:00:00', 440, true, null, true, 30, 6200000, 17500000],
            ['GP311', 'GPA', 'CGK', 'ICN', '22:00:00', 420, true, null, true, 30, 5400000, 15000000],
            ['GP312', 'GPA', 'ICN', 'CGK', '10:30:00', 430, true, null, true, 30, 5400000, 15000000],
            ['GP321', 'GPA', 'CGK', 'SYD', '21:30:00', 450, true, null, true, 30, 6900000, 18500000],
            ['GP322', 'GPA', 'SYD', 'CGK', '09:00:00', 460, true, null, true, 30, 6900000, 18500000],

            // ---- Tambahan demonstrasi ----
            ['GP331', 'GPA', 'DPS', 'SYD', '08:30:00', 330, true, $extra, true, 30, 4300000, 12000000],
            ['GP332', 'GPA', 'SYD', 'DPS', '14:00:00', 340, true, $extra, true, 30, 4300000, 12000000],
            ['GP341', 'GPA', 'SRG', 'HND', '05:30:00', 640, false, $transit, true, 30, 6900000, null],
            ['GP342', 'GPA', 'HND', 'SRG', '11:30:00', 650, false, $transit, true, 30, 6900000, null],
        ];

        foreach ($rows as [$no, $air, $from, $to, $dep, $dur, $direct, $note, $intl, $bag, $eco, $biz]) {
            Flight::firstOrCreate(['flight_number' => $no], [
                'airline_id' => $airlines[$air],
                'origin_airport_id' => $airports[$from],
                'destination_airport_id' => $airports[$to],
                'departure_time' => $dep,
                'duration_minutes' => $dur,
                'is_direct' => $direct,
                'connection_note' => $note,
                'is_international' => $intl,
                'baggage_kg' => $bag,
                'cabin_baggage_kg' => 7,
                'economy_price' => $eco,
                'business_price' => $biz,
                'is_demo' => true,
                'is_published' => true,
            ]);
        }
    }

    private function schedules(): void
    {
        $days = (int) config('travel.seed_days');
        $today = now()->startOfDay();

        foreach (Flight::all() as $flight) {
            DB::transaction(function () use ($flight, $days, $today) {
                for ($i = 0; $i < $days; $i++) {
                    $date = $today->copy()->addDays($i);
                    $dep = $date->copy()->setTimeFromTimeString($flight->departure_time);

                    $schedule = FlightSchedule::firstOrCreate(
                        ['flight_id' => $flight->id, 'service_date' => $date->toDateString()],
                        [
                            'departure_at' => $dep,
                            'arrival_at' => $dep->copy()->addMinutes($flight->duration_minutes),
                            'economy_price' => $this->dayPrice((int) $flight->economy_price, $date),
                            'business_price' => $flight->business_price
                                ? $this->dayPrice((int) $flight->business_price, $date) : null,
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

    /** Jumat, Sabtu, Minggu +10%. Murni integer, dibulatkan ke ribuan. */
    private function dayPrice(int $price, Carbon $date): int
    {
        $pct = in_array($date->dayOfWeek, [0, 5, 6], true) ? 110 : 100;

        return intdiv(intdiv($price * $pct, 100) + 500, 1000) * 1000;
    }

    private function seats(FlightSchedule $schedule): void
    {
        $now = now();
        $rows = [];
        $row = 1;

        if ($schedule->business_price) {
            for ($i = 0; $i < (int) config('travel.flight.business_rows'); $i++, $row++) {
                foreach (['A', 'C', 'D', 'F'] as $letter) {
                    $rows[] = $this->seatRow($schedule->id, $row.$letter, 'Business', (int) $schedule->business_price, $now);
                }
            }
        }

        for ($i = 0; $i < (int) config('travel.flight.economy_rows'); $i++, $row++) {
            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $letter) {
                $rows[] = $this->seatRow($schedule->id, $row.$letter, 'Economy', (int) $schedule->economy_price, $now);
            }
        }

        // insertOrIgnore + unique (jadwal, nomor kursi) = aman bila dijalankan ulang.
        FlightSeat::insertOrIgnore($rows);
    }

    private function seatRow(int $scheduleId, string $number, string $cabin, int $price, $now): array
    {
        return [
            'flight_schedule_id' => $scheduleId,
            'seat_number' => $number,
            'cabin_class' => $cabin,
            'price' => $price,
            'status' => 'available',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}