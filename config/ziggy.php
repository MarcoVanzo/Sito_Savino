<?php

/*
|--------------------------------------------------------------------------
| Ziggy: quali rotte arrivano al browser
|--------------------------------------------------------------------------
|
| `@routes` stampa in ogni pagina l'elenco delle rotte con nome e indirizzo.
| Fino al 5 ottobre 2026 si escludevano pannello, Livewire, webhook e simili
| e passava tutto il resto: 268 rotte, comprese quelle che nessuna pagina
| chiama (parere del 5/10/2026, «esporre solo le rotte usate»). Ora è un
| elenco chiuso: i nomi che il frontend chiama con `route()`, `current()` o
| `routeName:`, ciascuno con la sua versione inglese `en.` se esiste (la
| sceglie il `route()` di app.js).
|
| Una rotta nuova chiamata dal frontend va aggiunta qui: lo controlla
| `tests/Feature/RotteNelBrowserTest.php`, che legge resources/js. I nomi
| costruiti a runtime (SeasonNav.vue, Partita.vue) li legge come stringhe
| letterali: un nome composto pezzo per pezzo va scritto qui a mano.
|
*/

$nomi = [
    'account.payment-verification',
    'account.payment-verification.store',
    'comunicazione.accrediti.submit',
    'consenso-cookie.registra',
    'contatti',
    'contatti.submit',
    'dashboard',
    'gallery',
    'gallery.album',
    'gallery.atleta',
    'home',
    'login',
    'logout',
    'news.index',
    'news.show',
    'newsletter.subscribe',
    'pages.show',
    'password.change',
    'password.change.update',
    'password.confirm',
    'password.email',
    'password.request',
    'password.store',
    'password.update',
    'profile.destroy',
    'profile.edit',
    'profile.update',
    'recesso',
    'recesso.store',
    'register',
    'shop',
    'shop.account',
    'shop.account.destroy',
    'shop.account.export',
    'shop.auction-checkout.show',
    'shop.auction-checkout.store',
    'shop.auctions.bid',
    'shop.auctions.index',
    'shop.auctions.show',
    'shop.cart',
    'shop.cart.count',
    'shop.cart.data',
    'shop.cart.destroy',
    'shop.cart.store',
    'shop.cart.update',
    'shop.category',
    'shop.checkout',
    'shop.checkout.retry',
    'shop.checkout.store',
    'shop.checkout.validate-coupon',
    'shop.order.receipt',
    'shop.order.show',
    'shop.orders',
    'shop.product',
    'shop.register',
    'shop.register.store',
    'shop.search',
    'stagione',
    'stagione.atleta',
    'stagione.cev',
    'stagione.classifica',
    'stagione.coppa-italia',
    'stagione.partita',
    'stagione.playoff',
    'stagione.risultati',
    'ticketing.page',
    'verification.send',
];

return [
    'only' => [...$nomi, ...array_map(fn (string $nome): string => 'en.'.$nome, $nomi)],
];
