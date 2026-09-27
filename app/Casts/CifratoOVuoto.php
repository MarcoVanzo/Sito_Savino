<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Come il cast `encrypted`, ma un valore che non si decifra piu' vale null.
 *
 * Serve ai token che si possono sempre rifare (il collegamento con Meta):
 * dopo la rotazione della APP_KEY del 27/09/2026 il token salvato con la
 * chiave vecchia mandava in 500 la pagina Analytics Social ("The MAC is
 * invalid") — proprio la pagina da cui si ricollega l'account — e anche il
 * salvataggio del token nuovo, perche' Eloquent decifra il valore originale
 * per capire se e' cambiato. Null significa "da ricollegare", che e' vero.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class CifratoOVuoto implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            Log::warning('Valore cifrato non piu\' leggibile: vale come assente', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'attributo' => $key,
            ]);

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : Crypt::encryptString((string) $value);
    }
}
