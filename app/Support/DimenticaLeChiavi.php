<?php

namespace App\Support;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Butta un elenco di chiavi di cache con una sola query.
 *
 * Con la cache sul database ogni `Cache::forget()` è una DELETE: salvare una
 * notizia dal pannello ne faceva più di cento (liste per categoria, pagina e
 * lingua), segnalate da Sentry come N+1 sul 10/10/2026. Il driver ha già la
 * DELETE con l'elenco (`forgetMany`), ma non la espone: qui si rifà la stessa,
 * comprese le chiavi gemelle di `Cache::flexible()`.
 *
 * Con un altro driver (Redis in locale, array nei test) si dimentica chiave
 * per chiave, che lì non costa una query.
 */
final class DimenticaLeChiavi
{
    /**
     * @param  iterable<string>  $chiavi
     */
    public static function insieme(iterable $chiavi): void
    {
        $chiavi = array_values(array_unique([...$chiavi]));

        if ($chiavi === []) {
            return;
        }

        $nome = (string) config('cache.default');
        $store = Cache::store($nome)->getStore();

        if (! $store instanceof DatabaseStore) {
            foreach ($chiavi as $chiave) {
                Cache::forget($chiave);
            }

            return;
        }

        $prefisso = $store->getPrefix();
        $righe = [];

        foreach ($chiavi as $chiave) {
            $righe[] = $prefisso.$chiave;
            $righe[] = $prefisso.Repository::FLEXIBLE_CREATED_KEY_PREFIX.$chiave;
        }

        $store->getConnection()
            ->table((string) config("cache.stores.{$nome}.table", 'cache'))
            ->whereIn('key', $righe)
            ->delete();
    }
}
