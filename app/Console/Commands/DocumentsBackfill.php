<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\DocumentService;
use Illuminate\Console\Command;

class DocumentsBackfill extends Command
{
    protected $signature = 'documents:backfill';

    protected $description = 'Buat receipt dan tiket untuk pesanan terbayar yang belum memilikinya (idempoten)';

    public function handle(DocumentService $documents): int
    {
        $count = 0;

        Booking::where('status', Booking::CONFIRMED)
            ->where(fn ($q) => $q->whereDoesntHave('receipt')->orWhereDoesntHave('tickets'))
            ->orderBy('id')
            ->get()
            ->each(function (Booking $b) use ($documents, &$count) {
                if ($documents->ensureIssued($b)) {
                    $count++;
                }
            });

        $this->info("Pesanan yang dilengkapi dokumennya: {$count}");

        return self::SUCCESS;
    }
}