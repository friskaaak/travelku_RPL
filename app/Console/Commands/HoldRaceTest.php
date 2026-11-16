<?php

namespace App\Console\Commands;

use App\Models\FlightSchedule;
use App\Models\FlightSeat;
use App\Models\Hold;
use App\Models\Hotel;
use App\Models\RoomNight;
use App\Models\TrainSchedule;
use App\Models\TrainSeat;
use App\Models\User;
use App\Services\Inventory\HoldService;
use App\Services\Inventory\HotelAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class HoldRaceTest extends Command
{
    protected $signature = 'holds:race-test {service=flight : flight, train, atau hotel} {--workers=6 : jumlah proses serentak (2-10)}';

    protected $description = 'Uji balapan: beberapa proses serentak berebut satu kursi atau kamar. Hanya untuk lingkungan local.';

    public function handle(HoldService $holds, HotelAvailability $availability): int
    {
        if (! app()->environment('local')) {
            $this->error('Perintah ini hanya boleh dijalankan di lingkungan local.');

            return self::FAILURE;
        }

        $service = $this->argument('service');
        if (! in_array($service, ['flight', 'train', 'hotel'], true)) {
            $this->error('Layanan harus flight, train, atau hotel.');

            return self::FAILURE;
        }

        $workers = max(2, min(10, (int) $this->option('workers')));

        $target = match ($service) {
            'flight' => $this->flightTarget(),
            'train' => $this->trainTarget(),
            'hotel' => $this->hotelTarget($availability),
        };

        if (! $target) {
            return self::FAILURE;
        }

        $this->info("Target: {$target['label']}");
        $this->info("Menjalankan {$workers} proses serentak. Tunggu sekitar 10 detik...");

        $users = [];
        for ($i = 1; $i <= $workers; $i++) {
            $user = new User();
            $user->forceFill([
                'name' => "Uji Balapan {$i}",
                'email' => 'race'.$i.'.'.Str::lower(Str::random(6)).'@travelku.test',
                'password' => Str::random(32),
                'role' => 'customer',
            ])->save();
            $users[] = $user;
        }
        $ids = array_map(fn ($u) => $u->id, $users);

        try {
            // Semua proses menunggu sampai detik yang sama agar benar-benar serentak.
            $startAt = microtime(true) + 8;
            $procs = [];

            foreach ($users as $user) {
                $cmd = array_merge(
                    [PHP_BINARY, base_path('artisan'), 'holds:race-worker', $service, (string) $user->id],
                    $target['args'],
                    ['--at='.number_format($startAt, 3, '.', '')]
                );

                $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

                if (! is_resource($proc)) {
                    $this->error('Gagal menjalankan proses anak. Pastikan fungsi proc_open tidak dinonaktifkan di php.ini.');

                    return self::FAILURE;
                }

                $procs[] = [$proc, $pipes, $user];
            }

            $rows = [];
            $wins = 0;

            foreach ($procs as [$proc, $pipes, $user]) {
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);

                $line = collect(preg_split('/\R/', trim((string) $out)))
                    ->first(fn ($l) => preg_match('/^(OK|FAIL|ERROR)/', $l))
                    ?? 'ERROR: tanpa hasil. '.Str::limit(trim((string) $err), 80);

                if ($line === 'OK') {
                    $wins++;
                }

                $rows[] = [$user->name, Str::limit($line, 90)];
            }

            $this->table(['Pekerja', 'Hasil'], $rows);

            $activeHolds = Hold::whereIn('user_id', $ids)->where('status', Hold::ACTIVE)->count();
            $this->line("Pemenang menurut proses : {$wins}");
            $this->line("Hold aktif di database  : {$activeHolds}");

            if ($wins === 1 && $activeHolds === 1) {
                $this->info('LULUS: tepat 1 pemenang. Tidak ada double-booking.');
                $ok = self::SUCCESS;
            } else {
                $this->error('GAGAL: pemenang seharusnya tepat 1. Periksa baris ERROR pada tabel di atas.');
                $ok = self::FAILURE;
            }
        } finally {
            // Bersihkan semua data uji.
            Hold::whereIn('user_id', $ids)->get()->each(fn ($h) => $holds->release($h, Hold::RELEASED, 'Uji balapan selesai'));
            ($target['cleanup'])();
            User::whereIn('id', $ids)->delete();
        }

        return $ok;
    }

    private function flightTarget(): ?array
    {
        $schedule = FlightSchedule::where('status', 'scheduled')
            ->where('departure_at', '>', now()->addHour())
            ->orderBy('departure_at')->first();

        $seat = $schedule
            ? FlightSeat::where('flight_schedule_id', $schedule->id)->where('status', 'available')->orderBy('id')->first()
            : null;

        if (! $schedule || ! $seat) {
            $this->error('Belum ada jadwal pesawat dengan kursi kosong. Jalankan: php artisan db:seed --class=FlightSeeder');

            return null;
        }

        return [
            'label' => "jadwal pesawat #{$schedule->id}, kursi {$seat->seat_number}",
            'args' => [(string) $schedule->id, $seat->seat_number],
            'cleanup' => fn () => null,
        ];
    }

    private function trainTarget(): ?array
    {
        $schedule = TrainSchedule::where('status', 'scheduled')
            ->where('departure_at', '>', now()->addHour())
            ->orderBy('departure_at')->first();

        $seat = $schedule
            ? TrainSeat::where('train_schedule_id', $schedule->id)->where('status', 'available')->orderBy('id')->first()
            : null;

        if (! $schedule || ! $seat) {
            $this->error('Belum ada jadwal kereta dengan kursi kosong. Jalankan: php artisan db:seed --class=TrainSeeder');

            return null;
        }

        return [
            'label' => "jadwal kereta #{$schedule->id}, kursi {$seat->seat_number}",
            'args' => [(string) $schedule->id, $seat->seat_number],
            'cleanup' => fn () => null,
        ];
    }

    /**
     * Hotel: semua kamar Family kecuali satu diblokir sementara (status confirmed),
     * sehingga tersisa tepat satu kamar yang diperebutkan.
     */
    private function hotelTarget(HotelAvailability $availability): ?array
    {
        $hotel = Hotel::orderBy('id')->first();
        if (! $hotel) {
            $this->error('Belum ada hotel. Jalankan: php artisan db:seed --class=HotelSeeder');

            return null;
        }

        $in = now()->addDays(40)->startOfDay();
        $out = $in->copy()->addDays(2);

        $free = $availability->availableRoomsQuery($in, $out, 1)
            ->where('hotel_id', $hotel->id)->where('room_type', 'Family')
            ->orderBy('room_number')->get();

        if ($free->isEmpty()) {
            $this->error('Tidak ada kamar Family yang kosong pada tanggal uji.');

            return null;
        }

        $block = $free->slice(1);
        $nights = [$in->toDateString(), $in->copy()->addDay()->toDateString()];

        foreach ($block as $room) {
            foreach ($nights as $night) {
                RoomNight::create(['room_id' => $room->id, 'night' => $night, 'status' => 'confirmed']);
            }
        }

        return [
            'label' => "{$hotel->name}, 1 kamar Family tersisa untuk {$in->toDateString()} sampai {$out->toDateString()}",
            'args' => [(string) $hotel->id, 'Family', $in->toDateString(), $out->toDateString()],
            'cleanup' => fn () => RoomNight::whereIn('room_id', $block->pluck('id'))
                ->whereIn('night', $nights)->where('status', 'confirmed')->whereNull('booking_id')->delete(),
        ];
    }
}