<?php

namespace App\Models;

use App\Enums\EventoDellaDiretta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Un contatore del report degli sponsor della diretta. Anonimo per
 * costruzione: gara, creatività, evento, giorno e un numero.
 */
class LiveAdStat extends Model
{
    protected $fillable = ['game_id', 'live_creative_id', 'sponsor_id', 'sponsor_nome', 'creativita_nome', 'evento', 'giorno', 'conteggio'];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'evento' => EventoDellaDiretta::class,
            'giorno' => 'date',
            'conteggio' => 'integer',
        ];
    }

    /**
     * Più uno, in una sola istruzione: mille spettatori che vedono la stessa
     * cornice nello stesso secondo non si pestano i piedi.
     */
    public static function conta(int $gara, int $creativita, EventoDellaDiretta $evento, ?Carbon $quando = null): void
    {
        $adesso = now();

        DB::table('live_ad_stats')->upsert(
            [[
                'game_id' => $gara,
                'live_creative_id' => $creativita,
                'evento' => $evento->value,
                'giorno' => ($quando ?? $adesso)->toDateString(),
                'conteggio' => 1,
                'created_at' => $adesso,
                'updated_at' => $adesso,
            ]],
            ['game_id', 'live_creative_id', 'evento', 'giorno'],
            ['conteggio' => DB::raw('conteggio + 1'), 'updated_at' => $adesso],
        );
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<LiveCreative, $this> */
    public function creativita(): BelongsTo
    {
        return $this->belongsTo(LiveCreative::class, 'live_creative_id');
    }
}
