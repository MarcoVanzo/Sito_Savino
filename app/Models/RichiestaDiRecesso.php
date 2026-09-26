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
     * Quanto si tiene una dichiarazione che non si aggancia a nessun ordine
     * (numero sbagliato o inventato): dodici mesi dall'invio. Non prova
     * nessun recesso su un contratto che esista, e basta a chiudere il caso.
     */
    public const MESI_DI_CONSERVAZIONE = 12;

    /**
     * Quanto si tiene una dichiarazione agganciata a un ordine vero: dieci
     * anni dall'invio. E' la prova che il cliente ha receduto, e il diritto
     * al rimborso che ne nasce si prescrive in dieci anni (art. 2946 c.c.):
     * buttarla prima lascerebbe la societa' senza la data del recesso proprio
     * quando le servirebbe. Deciso dal titolare il 26/09/2026.
     *
     * Il criterio e' `order_id`, che il controller valorizza solo quando il
     * numero scritto nel modulo corrisponde a un ordine. Le toglie
     * `model:prune`, ogni notte: dal pannello non si cancellano.
     */
    public const ANNI_DI_CONSERVAZIONE_CON_ORDINE = 10;

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
        return static::where(fn (Builder $query) => $query
            ->where(fn (Builder $senzaOrdine) => $senzaOrdine
                ->whereNull('order_id')
                ->where('inviata_il', '<', now()->subMonths(self::MESI_DI_CONSERVAZIONE)))
            ->orWhere(fn (Builder $conOrdine) => $conOrdine
                ->whereNotNull('order_id')
                ->where('inviata_il', '<', now()->subYears(self::ANNI_DI_CONSERVAZIONE_CON_ORDINE))));
    }
}
