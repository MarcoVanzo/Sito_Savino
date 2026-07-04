<?php

namespace App\Console\Commands;

use App\Services\AuctionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAuctionPayments extends Command
{
    protected $signature = 'auction:check-payments';

    protected $description = 'Verifica i pagamenti dei vincitori aste e assegna al prossimo offerente se scaduti';

    public function handle(AuctionService $auctionService): int
    {
        $count = $auctionService->checkWinnerPayments();

        $message = "Pagamenti vincitori verificati: {$count}";

        $this->info($message);
        Log::info("[auction:check-payments] {$message}");

        return self::SUCCESS;
    }
}
