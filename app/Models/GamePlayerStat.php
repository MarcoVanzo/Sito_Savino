<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Statistiche di una giocatrice in una singola gara, dal tabellino della Lega.
 *
 * Le righe esistono per entrambe le squadre, perché nella scheda della partita
 * servono anche i numeri delle avversarie. `player_id` è valorizzato solo per le
 * nostre atlete: lo storico individuale si costruisce su quelle.
 */
class GamePlayerStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id', 'team_id', 'player_id', 'player_name', 'jersey_number',
        'is_captain', 'is_libero', 'sets_played',
        'points_total', 'points_break', 'points_win_loss',
        'serve_total', 'serve_errors', 'serve_points',
        'reception_total', 'reception_errors', 'reception_positive_pct', 'reception_perfect_pct',
        'attack_total', 'attack_errors', 'attack_blocked', 'attack_points', 'attack_pct',
        'block_points', 'synced_at',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
        'is_libero' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Solo le righe agganciate a un'atleta del club: sono le uniche che
     * alimentano lo storico individuale.
     */
    public function scopeOfOwnPlayers(Builder $query): Builder
    {
        return $query->whereNotNull('player_id');
    }

    /**
     * Punti realizzati in attacco, a muro e in battuta: la scomposizione che
     * si mostra nella scheda gara.
     */
    public function pointsBreakdown(): array
    {
        return [
            'attack' => $this->attack_points,
            'block' => $this->block_points,
            'serve' => $this->serve_points,
        ];
    }
}
