<?php

namespace App\Console\Commands;

use App\Services\AuctionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseAuctions extends Command
{
    protected $signature = 'auction:close';

    protected $description = 'Chiude le aste attive la cui data di fine è passata e assegna i vincitori';

    public function handle(AuctionService $auctionService): int
    {
        $count = $auctionService->closeEndedAuctions();

        $message = "Aste chiuse: {$count}";

        $this->info($message);
        Log::info("[auction:close] {$message}");

        return self::SUCCESS;
    }
}
