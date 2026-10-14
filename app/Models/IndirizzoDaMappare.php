<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un vecchio indirizzo del sito WordPress ancora richiesto, che nessun
 * redirect porta su un contenuto preciso (Amministrazione → 404 da mappare).
 *
 * Una riga per percorso con un contatore, scritta da `RedirectGestiti`: i 404
 * in stile WordPress e le visite alle rotte generiche di `legacy.php` (`/tag/…`
 * → `/news`), che non sono errori ma neanche una destinazione. Non è un log:
 * niente IP né user agent, e chi non torna da sei mesi sparisce da solo.
 *
 * @property int $id
 * @property string $percorso
 * @property string $origine
 * @property int $hits
 * @property Carbon|null $last_hit_at
 */
class IndirizzoDaMappare extends Model
{
    use MassPrunable;

    public const ORIGINE_404 = '404';

    public const ORIGINE_LEGACY = 'legacy';

    /** Oltre questo numero di righe non se ne aggiungono altre (si contano solo quelle già presenti). */
    public const MASSIMO_DI_RIGHE = 5000;

    protected $table = 'redirect_mancanti';

    protected $fillable = [
        'percorso',
        'origine',
        'hits',
        'last_hit_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * Chi non è stato richiesto negli ultimi sei mesi non vale un redirect.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('last_hit_at', '<', now()->subMonths(6));
    }
}
