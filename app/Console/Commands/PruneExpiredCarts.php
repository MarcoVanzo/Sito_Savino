<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneExpiredCarts extends Command
{
    protected $signature = 'carts:prune-expired';

    protected $description = 'Elimina carrelli scaduti e i relativi articoli';

    public function handle(): int
    {
        $expiredCarts = Cart::where('expires_at', '<', now())
            ->with('items')
            ->get();

        if ($expiredCarts->isEmpty()) {
            $this->info('Nessun carrello scaduto trovato.');

            return self::SUCCESS;
        }

        $cartCount = 0;
        $itemCount = 0;

        foreach ($expiredCarts as $cart) {
            $items = $cart->items()->count();
            $cart->items()->delete();
            $cart->delete();

            $itemCount += $items;
            $cartCount++;
        }

        $this->info("Eliminati {$cartCount} carrelli scaduti con {$itemCount} articoli.");
        Log::info("PruneExpiredCarts: eliminati {$cartCount} carrelli scaduti con {$itemCount} articoli.");

        return self::SUCCESS;
    }
}
