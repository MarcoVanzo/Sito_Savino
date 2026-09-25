<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
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
    use MassPrunable;

    /**
     * Quanto si tiene una dichiarazione: dodici mesi dall'invio, come dice
     * l'informativa. Il recesso si chiude in poche settimane — 14 giorni per
     * rispedire, 14 per rimborsare — e il rimborso resta registrato
     * sull'ordine, che ha la sua conservazione fiscale. Le toglie
     * `model:prune`, ogni notte: dal pannello non si cancellano.
     */
    public const MESI_DI_CONSERVAZIONE = 12;

    protected $table = 'richieste_di_recesso';

    protected $fillable = [
        'order_id', 'numero_ordine', 'nome', 'email', 'articoli', 'lingua',
        'inviata_il', 'gestita_il', 'note_interne',
    ];

    protected $casts = [
        'inviata_il' => 'datetime',
        'gestita_il' => 'datetime',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * L'indirizzo a cui è intestato l'ordine agganciato, se c'è.
     */
    public function emailDellOrdine(): ?string
    {
        $ordine = $this->order;

        return $ordine ? ($ordine->guest_email ?: $ordine->user?->email) : null;
    }

    /**
     * La dichiarazione arriva da un indirizzo diverso da quello dell'ordine.
     *
     * Non la invalida (la legge non chiede di dimostrare chi si è), ma chi la
     * gestisce deve saperlo prima di rimborsare: chiunque conosca un numero
     * d'ordine può scriverlo nel modulo.
     */
    public function emailDiversaDaQuellaDellOrdine(): bool
    {
        $email = $this->emailDellOrdine();

        return $email !== null && strcasecmp(trim($email), trim($this->email)) !== 0;
    }

    public function prunable(): Builder
    {
        return static::where('inviata_il', '<', now()->subMonths(self::MESI_DI_CONSERVAZIONE));
    }
}
