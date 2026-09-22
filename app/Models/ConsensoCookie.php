<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Una scelta fatta sul banner dei cookie, con quanto serve a dimostrarla.
 *
 * Il modello non usa `updated_at`: un consenso non si modifica, se ne registra
 * uno nuovo. La storia di chi cambia idea resta leggibile riga per riga.
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
     */
    public const VERSIONE = '2026-09-22';

    protected $fillable = [
        'riferimento',
        'statistiche',
        'marketing',
        'azione',
        'versione',
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

    /**
     * L'impronta dell'indirizzo, con il sale dell'applicazione.
     *
     * `APP_KEY` non lascia mai il server, quindi l'impronta non è ricostruibile
     * da fuori nemmeno conoscendo l'indirizzo. Serve a contare e a distinguere,
     * non a risalire alla persona.
     */
    public static function improntaDi(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.'|'.config('app.key'));
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
