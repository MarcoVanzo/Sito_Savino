<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * Una scelta fatta sul banner dei cookie, con quanto serve a dimostrarla.
 *
 * Il modello non usa `updated_at`: un consenso non si modifica, se ne registra
 * uno nuovo. La storia di chi cambia idea resta leggibile riga per riga.
 *
 * Le righe si scrivono solo da `CatenaDeiConsensi::registra()`, che le mette in
 * coda alla catena delle impronte; il modello rifiuta modifiche e
 * cancellazioni una per una (la potatura per età toglie una testa intera e
 * lascia l'ancora, vedi `consensi:pota`).
 */
class ConsensoCookie extends Model
{
    protected $table = 'consensi_cookie';

    public const UPDATED_AT = null;

    /**
     * La versione dei testi dell'informativa a cui si riferisce il consenso.
     *
     * Si alza quando cambia ciò che si dichiara al visitatore — un tracker
     * nuovo, una finalità diversa: i consensi raccolti su una versione
     * precedente restano validi come prova di ciò che è stato chiesto allora,
     * e il banner torna a mostrarsi.
     *
     * 2026-09-23: la Cookie Policy dice adesso che mappa e video incorporati si
     * caricano insieme alla pagina e fanno arrivare l'IP a chi li ospita, e che
     * i caratteri tipografici non arrivano più dal CDN di Google. Cambia ciò
     * che si dichiara, quindi la scelta va richiesta.
     *
     * 2026-09-26: mappa e video aspettano il consenso di marketing (o un clic
     * sul segnaposto) invece di partire con la pagina. Il consenso di
     * marketing dato prima copriva solo il pixel di Meta: adesso fa caricare
     * anche YouTube e Google Maps, quindi va richiesto su questa versione.
     */
    public const VERSIONE = '2026-09-26';

    protected $fillable = [
        'riferimento',
        'statistiche',
        'marketing',
        'azione',
        'versione',
        'impronta_testi',
        'locale',
        'user_agent',
        'impronta_ip',
    ];

    protected function casts(): array
    {
        return [
            'statistiche' => 'boolean',
            'marketing' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Un consenso registrato non si modifica: se ne registra uno nuovo.');
        });

        static::deleting(function () {
            throw new LogicException('Un consenso non si cancella da solo: spezzerebbe la catena delle impronte (consensi:pota toglie le righe scadute).');
        });
    }

    /**
     * L'impronta dell'indirizzo, con un sale che non lascia mai il server:
     * l'impronta non è ricostruibile da fuori nemmeno conoscendo l'indirizzo.
     * Serve a contare e a distinguere, non a risalire alla persona.
     */
    public static function improntaDi(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.'|'.self::sale());
    }

    /**
     * Il sale dell'impronta: `CONSENSI_SALE` (services.consensi.sale).
     *
     * Fino al 2 ottobre 2026 era `APP_KEY`, che è stata esposta e va ruotata:
     * con il sale legato alla chiave, ruotarla avrebbe reso incomparabili le
     * impronte già registrate. Se il segreto dedicato manca si ripiega su
     * `APP_KEY`, apposta: il deploy non si rompe, e le impronte restano quelle
     * di prima finché qualcuno non imposta la variabile.
     */
    public static function sale(): string
    {
        $sale = (string) config('services.consensi.sale');

        return $sale !== '' ? $sale : (string) config('app.key');
    }

    /**
     * Che cosa è successo, a leggere le due caselle facoltative.
     */
    public static function azionePer(bool $statistiche, bool $marketing, bool $primaVolta): string
    {
        if (! $statistiche && ! $marketing) {
            return $primaVolta ? 'rifiutato' : 'revocato';
        }

        return $primaVolta ? 'concesso' : 'aggiornato';
    }

    public static function nuovoRiferimento(): string
    {
        return (string) Str::uuid();
    }
}
