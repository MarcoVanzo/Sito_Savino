<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hash di una password usata in passato da un utente.
 *
 * Serve a impedire il riuso delle ultime N password (config
 * `password_policy.history_size`). Non contiene mai password in chiaro.
 */
class PasswordHistory extends Model
{
    /** Solo created_at: una voce di storico non viene mai aggiornata. */
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'password'];

    protected $hidden = ['password'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
