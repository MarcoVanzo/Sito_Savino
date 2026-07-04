<?php

namespace App\Console\Commands;

use App\Services\AuctionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActivateAuctions extends Command
{
    protected $signature = 'auction:activate';

    protected $description = 'Attiva le aste programmate la cui data di inizio è passata';

    public function handle(AuctionService $auctionService): int
    {
        $count = $auctionService->activateScheduledAuctions();

        $message = "Aste attivate: {$count}";

        $this->info($message);
        Log::info("[auction:activate] {$message}");

        return self::SUCCESS;
    }
}
