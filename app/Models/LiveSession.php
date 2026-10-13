<?php

namespace App\Models;

use App\Enums\StatoDellaDiretta;
use App\Models\Traits\LogsActivity;
use App\Support\Diretta\ImpostazioniDellaDiretta;
use App\Support\MatchDay;
use Carbon\Carbon;
use Database\Factories\LiveSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * La diretta con sponsor di una gara: impostazioni (pre-roll, ciclo della
 * cornice) e stato deciso dalla regia.
 *
 * Nasce con i valori globali del gruppo `diretta` (`perLaGara()`) e poi la
 * redazione la cambia gara per gara. Il ciclo parte qualche minuto dopo il
 * fischio d'inizio e finisce tre ore dopo (come il Match Day).
 */
class LiveSession extends Model
{
    /** @use HasFactory<LiveSessionFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'game_id',
        'preroll_attivo',
        'preroll_salta_dopo_secondi',
        'ciclo_attivo',
        'ciclo_ogni_minuti',
        'ciclo_durata_secondi',
        'ciclo_ritardo_minuti',
        'ciclo_inizio',
        'ciclo_fine',
        'pausa_durata_predefinita_secondi',
        'stato',
        'creativita_in_onda_id',
        'pausa_dalle',
        'pausa_fino_alle',
        'versione',
    ];

    /** La versione cambia a ogni scrittura: nel registro sarebbe solo rumore. */
    protected array $logExclude = ['versione'];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'preroll_attivo' => 'boolean',
            'preroll_salta_dopo_secondi' => 'integer',
            'ciclo_attivo' => 'boolean',
            'ciclo_ogni_minuti' => 'integer',
            'ciclo_durata_secondi' => 'integer',
            'ciclo_ritardo_minuti' => 'integer',
            'ciclo_inizio' => 'datetime',
            'ciclo_fine' => 'datetime',
            'pausa_durata_predefinita_secondi' => 'integer',
            'stato' => StatoDellaDiretta::class,
            'pausa_dalle' => 'datetime',
            'pausa_fino_alle' => 'datetime',
            'versione' => 'integer',
        ];
    }

    /**
     * La diretta della gara, creata con i valori di partenza se non c'è.
     */
    public static function perLaGara(Game $game): self
    {
        return self::query()->firstOrCreate(['game_id' => $game->id], self::valoriDiPartenza($game));
    }

    /**
     * La diretta della gara così com'è, o com'è nascerebbe, senza scriverla:
     * aprire la scheda per guardare non deve creare righe né voci nel registro.
     */
    public static function perLaGaraSenzaCrearla(Game $game): self
    {
        return self::query()->where('game_id', $game->id)->first()
            ?? new self(['game_id' => $game->id, ...self::valoriDiPartenza($game)]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function valoriDiPartenza(Game $game): array
    {
        $predefiniti = ImpostazioniDellaDiretta::perUnaGaraNuova();

        return [
            ...$predefiniti,
            ...self::orariDelCiclo($game, $predefiniti['ciclo_ritardo_minuti']),
            'preroll_attivo' => true,
            'stato' => StatoDellaDiretta::InOnda,
            'versione' => 1,
        ];
    }

    /**
     * Inizio e fine del ciclo calcolati dall'orario della gara.
     *
     * @return array{ciclo_inizio: Carbon, ciclo_fine: Carbon}
     */
    public static function orariDelCiclo(Game $game, int $ritardoMinuti): array
    {
        return [
            'ciclo_inizio' => $game->match_date->copy()->addMinutes($ritardoMinuti),
            'ciclo_fine' => $game->match_date->copy()->addHours(MatchDay::ORE_DOPO_L_INIZIO),
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<LiveCreative, $this> */
    public function creativitaInOnda(): BelongsTo
    {
        return $this->belongsTo(LiveCreative::class, 'creativita_in_onda_id');
    }

    /** @return BelongsToMany<LiveCreative, $this> */
    public function creativitaDelPreroll(): BelongsToMany
    {
        return $this->belongsToMany(LiveCreative::class, 'live_session_preroll')
            ->withPivot('ordine')
            ->orderByPivot('ordine');
    }

    /** @return BelongsToMany<LiveCreative, $this> */
    public function creativitaDellaGara(): BelongsToMany
    {
        return $this->belongsToMany(LiveCreative::class, 'live_session_pausa')
            ->withPivot('ordine')
            ->orderByPivot('ordine');
    }

    /**
     * Mette le creatività di un elenco nell'ordine dato.
     *
     * @param  'creativitaDelPreroll'|'creativitaDellaGara'  $elenco
     * @param  list<int>  $ids
     */
    public function ordina(string $elenco, array $ids): void
    {
        $righe = [];

        foreach (array_values(array_unique($ids)) as $posizione => $id) {
            $righe[$id] = ['ordine' => $posizione];
        }

        $this->{$elenco}()->sync($righe);
    }
}
