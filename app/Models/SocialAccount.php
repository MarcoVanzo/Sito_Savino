<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un account social Meta collegato: una Pagina Facebook e, quando c'è, il
 * profilo Instagram business che le è associato.
 *
 * Il token è cifrato a riposo: legge gli insight di una Pagina reale e vale
 * quanto una credenziale. Il cast `encrypted` lo tiene fuori dai dump del
 * database e dai log che stampano gli attributi del model.
 *
 * @property int $id
 * @property string $name
 * @property string|null $page_id
 * @property string|null $ig_account_id
 * @property string|null $access_token
 * @property Carbon|null $token_expires_at
 */
class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'page_id', 'page_name', 'ig_account_id', 'ig_username',
        'access_token', 'token_expires_at', 'connected_by', 'connected_at',
        'last_synced_at', 'sort',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return HasMany<SocialInsightDaily, $this> */
    public function daily(): HasMany
    {
        return $this->hasMany(SocialInsightDaily::class);
    }

    /** @return BelongsTo<User, $this> */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /**
     * Un account è utilizzabile se ha un token e il token non è scaduto.
     * I token di Pagina derivati da un token utente long-lived non hanno
     * scadenza: `token_expires_at` nullo non è un difetto.
     */
    public function isConnected(): bool
    {
        if (blank($this->access_token)) {
            return false;
        }

        return $this->token_expires_at === null || $this->token_expires_at->isFuture();
    }

    public function hasInstagram(): bool
    {
        return filled($this->ig_account_id);
    }

    /**
     * @param  Builder<SocialAccount>  $query
     * @return Builder<SocialAccount>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }
}
