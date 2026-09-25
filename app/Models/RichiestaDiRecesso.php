<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una dichiarazione di recesso inviata con la funzione online (art. 54-bis
 * del Codice del Consumo). Vedi la migrazione `richieste_di_recesso`.
 *
 * @property Carbon $inviata_il
 * @property Carbon|null $gestita_il
 */
class RichiestaDiRecesso extends Model
{
    protected $table = 'richieste_di_recesso';

    protected $fillable = [
        'order_id', 'numero_ordine', 'nome', 'email', 'articoli', 'lingua',
        'inviata_il', 'gestita_il', 'note_interne',
    ];

    protected $casts = [
        'inviata_il' => 'datetime',
        'gestita_il' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
