<?php

namespace App\Console\Commands;

use App\Exceptions\HoldException;
use App\Models\FlightSchedule;
use App\Models\Hotel;
use App\Models\TrainSchedule;
use App\Models\User;
use App\Services\Inventory\HoldService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class HoldRaceWorker extends Command
{
    protected $signature = 'holds:race-worker {service} {user} {args* : argumen target} {--at=0 : waktu mulai (unix, detik)}';

    protected $description = 'INTERNAL: dipanggil oleh holds:race-test. Jangan dijalankan manual.';

    protected $hidden = true;

    public function handle(HoldService $holds): int
    {
        if (! app()->environment('local')) {
            return self::FAILURE;
        }

        $service = $this->argument('service');
        $user = User::findOrFail((int) $this->argument('user'));
        $args = $this->argument('args');

        // Tunggu sampai detik mulai yang sama untuk semua proses.
        $at = (float) $this->option('at');
        while (microtime(true) < $at) {
            usleep(500);
        }

        try {
            match ($service) {
                'flight' => $holds->createFlightHold($user, FlightSchedule::findOrFail((int) $args[0]), [$args[1]], null),
                'train' => $holds->createTrainHold($user, TrainSchedule::findOrFail((int) $args[0]), [$args[1]], null),
                'hotel' => $holds->createHotelHold(
                    $user, Hotel::findOrFail((int) $args[0]), $args[1],
                    Carbon::createFromFormat('!Y-m-d', $args[2]), Carbon::createFromFormat('!Y-m-d', $args[3]),
                    1, 2, 0, null
                ),
            };

            $this->line('OK');
        } catch (HoldException $e) {
            $this->line('FAIL: '.$e->getMessage());
        } catch (\Throwable $e) {
            $this->line('ERROR: '.class_basename($e).': '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}