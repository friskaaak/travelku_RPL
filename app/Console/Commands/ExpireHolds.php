<?php

namespace App\Console\Commands;

use App\Services\Inventory\HoldService;
use Illuminate\Console\Command;

class ExpireHolds extends Command
{
    protected $signature = 'holds:expire';

    protected $description = 'Lepas hold yang waktunya sudah habis dan kembalikan inventori serta kuota diskon';

    public function handle(HoldService $holds): int
    {
        $count = $holds->expireDue();
        $this->info("Hold dilepas: {$count}");

        return self::SUCCESS;
    }
}