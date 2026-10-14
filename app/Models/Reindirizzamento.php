<?php

namespace App\Models;

use App\Enums\CodiceDelRedirect;
use App\Models\Traits\LogsActivity;
use App\Support\RedirectGestiti;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un vecchio indirizzo mappato a mano (Amministrazione → Redirect).
 *
 * Vince sulle rotte di `routes/pubbliche/legacy.php` e sul permalink
 * automatico delle notizie: serve proprio a correggere quello che le regole
 * generali non sanno indovinare. `from_path` si salva già normalizzato
 * (`RedirectGestiti::normalizza`), che è la stessa forma con cui si cerca.
 *
 * @property int $id
 * @property string $from_path
 * @property string|null $to_path
 * @property CodiceDelRedirect $status_code
 * @property int $hits
 * @property Carbon|null $last_hit_at
 */
class Reindirizzamento extends Model
{
    use LogsActivity;

    protected $table = 'redirects';

    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
    ];

    /** @var list<string> */
    protected array $logExclude = ['hits', 'last_hit_at'];

    protected string $logLabelField = 'from_path';

    /**
     * L'import da CSV ne scrive centinaia alla volta: nel registro va solo il
     * lavoro fatto dal pannello.
     */
    protected bool $logSoloDalPannello = true;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => CodiceDelRedirect::class,
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $redirect): void {
            $redirect->from_path = RedirectGestiti::normalizza((string) $redirect->from_path) ?? (string) $redirect->from_path;

            $destinazione = trim((string) $redirect->to_path);
            $redirect->to_path = $redirect->status_code === CodiceDelRedirect::Rimosso || $destinazione === ''
                ? null
                : $destinazione;
        });

        // Un indirizzo mappato non è più da mappare.
        static::saved(function (self $redirect): void {
            IndirizzoDaMappare::query()->where('percorso', $redirect->from_path)->delete();
        });
    }
}
