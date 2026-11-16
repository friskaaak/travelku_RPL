<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\EmailDeliveryService;
use Illuminate\Console\Command;

class EmailsBackfill extends Command
{
    protected $signature = 'emails:backfill';

    protected $description = 'Antrekan email untuk pesanan terkonfirmasi yang belum punya catatan email (idempoten)';

    public function handle(EmailDeliveryService $emails): int
    {
        $count = 0;

        Booking::where('status', Booking::CONFIRMED)
            ->whereHas('receipt')
            ->whereDoesntHave('emailDeliveries')
            ->orderBy('id')
            ->get()
            ->each(function (Booking $b) use ($emails, &$count) {
                $ids = $emails->createFor($b);
                $emails->dispatch($ids);
                $count++;
            });

        $this->info("Pesanan yang email-nya diantrekan: {$count}");

        return self::SUCCESS;
    }
}