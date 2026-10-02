<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Il testo che il sito presentava quando un consenso è stato dato.
 *
 * Una riga per impronta: la prima registrazione con un testo nuovo crea la
 * riga, le successive la ritrovano. Così l'archivio segue i testi davvero in
 * produzione, senza che qualcuno debba ricordarsi di archiviarli. Il contenuto
 * è la stringa esatta di cui `impronta` è lo sha256: chiunque può ricalcolarla.
 *
 * Le righe non si modificano e non si cancellano: sono la prova di che cosa
 * diceva il sito (EDPB 05/2020 §108). Il modello lo rifiuta, e le chiavi
 * esterne da `consensi_cookie` e `newsletter_subscribers` lo rifiutano anche
 * a chi passa dal database.
 */
class VersioneTestiConsenso extends Model
{
    protected $table = 'versioni_testi_consenso';

    public const UPDATED_AT = null;

    public const TIPO_COOKIE = 'cookie';

    public const TIPO_NEWSLETTER = 'newsletter';

    protected $fillable = ['tipo', 'impronta', 'contenuto'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Un testo archiviato non si modifica: se il sito dice altro, nasce una versione nuova.');
        });

        static::deleting(function () {
            throw new LogicException('Un testo archiviato non si cancella: è la prova di che cosa diceva il sito.');
        });
    }

    /**
     * Archivia il testo (se è nuovo) e ne restituisce l'impronta.
     *
     * `createOrFirst` e non `firstOrCreate`: due visitatori che scelgono nello
     * stesso istante dopo un rilascio cercano entrambi la riga, non la trovano
     * e la creano; il secondo deve ritrovare quella del primo, non andare in
     * errore sull'indice unico.
     *
     * @param  array<string, mixed>  $testi
     */
    public static function archivia(string $tipo, array $testi): string
    {
        $contenuto = self::serializza($testi);
        $impronta = hash('sha256', $contenuto);

        static::query()->createOrFirst(
            ['impronta' => $impronta],
            ['tipo' => $tipo, 'contenuto' => $contenuto],
        );

        return $impronta;
    }

    /**
     * La forma in cui il testo si conserva e si impronta: sempre la stessa per
     * lo stesso contenuto, leggibile a occhio (accenti e barre non escapati).
     *
     * @param  array<string, mixed>  $testi
     */
    public static function serializza(array $testi): string
    {
        return json_encode(
            $testi,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function testi(): array
    {
        return (array) json_decode($this->contenuto, true);
    }
}
