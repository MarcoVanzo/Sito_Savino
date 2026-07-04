<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canali Broadcast — Aste
|--------------------------------------------------------------------------
*/

Broadcast::channel('auction.{auctionId}', function () {
    return true; // Le aste sono pubbliche, chiunque può ascoltare
});
