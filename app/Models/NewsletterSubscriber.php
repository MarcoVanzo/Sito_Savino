<?php

namespace App\Models;

use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Jobs\UnsubscribeNewsletterFromActiveCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class NewsletterSubscriber extends Model
{
    use HasFactory;
    use MassPrunable;

    /**
     * Una richiesta d'iscrizione mai confermata si tiene trenta giorni: il
     * link vale sette, il resto è margine per chi scrive per chiedere un
     * nuovo invio. Oltre è solo un indirizzo (e un IP) di qualcuno che non ha
     * detto di sì — spesso di chi non sa nemmeno di essere stato scritto nel
     * modulo. Restano fuori le righe già passate da ActiveCampaign.
     */
    public const GIORNI_PER_CONFERMARE = 30;

    public function prunable(): Builder
    {
        return static::whereNull('confermato_il')
            ->whereNull('ac_contact_id')
            ->where('subscribed_at', '<', now()->subDays(self::GIORNI_PER_CONFERMARE));
    }

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'ip_address',
        'source',
        'synced_to_ac',
        'ac_contact_id',
        'subscribed_at',
        'confermato_il',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_to_ac' => 'boolean',
            'subscribed_at' => 'datetime',
            'confermato_il' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * La cancellazione del record locale deve arrivare anche ad ActiveCampaign:
     * lasciarlo lì significherebbe continuare a scrivere a chi ha chiesto di
     * essere dimenticato.
     */
    protected static function booted(): void
    {
        static::deleting(function (NewsletterSubscriber $subscriber) {
            if ($subscriber->ac_contact_id) {
                UnsubscribeNewsletterFromActiveCampaign::dispatch(
                    (int) $subscriber->ac_contact_id,
                    $subscriber->email,
                    deleteContact: true,
                );
            }

            // Nel log l'id, non l'indirizzo: il log non ha la conservazione
            // dell'archivio e finirebbe per tenere l'email di chi ha chiesto
            // di essere dimenticato.
            Log::channel('daily')->info('Cancellazione iscritto newsletter', [
                'subscriber_id' => $subscriber->id,
            ]);
        });
    }

    public function scopeSynced(Builder $query): Builder
    {
        return $query->where('synced_to_ac', true);
    }

    public function scopeUnsynced(Builder $query): Builder
    {
        return $query->where('synced_to_ac', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }

    public function scopeUnsubscribed(Builder $query): Builder
    {
        return $query->whereNotNull('unsubscribed_at');
    }

    /**
     * Solo gli iscritti che hanno confermato dal link ricevuto per email:
     * sono gli unici che possono arrivare ad ActiveCampaign.
     */
    public function scopeConfermati(Builder $query): Builder
    {
        return $query->whereNotNull('confermato_il');
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }

    public function haConfermato(): bool
    {
        return $this->confermato_il !== null;
    }

    /**
     * Il link che conferma l'iscrizione (doppio opt-in).
     *
     * Firmato e con scadenza di sette giorni: una conferma arrivata mesi dopo
     * non dimostra più che chi ha chiesto l'iscrizione e chi legge la casella
     * siano la stessa persona. Porta a una pagina con un pulsante, non esegue
     * da solo: i client di posta aprono i link per controllarli, e un GET che
     * conferma verrebbe "cliccato" da un filtro antispam.
     */
    public function confermaUrl(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (! in_array($locale, config('app.supported_locales', ['it']), true)) {
            $locale = 'it';
        }

        $namePrefix = $locale === 'it' ? '' : $locale.'.';

        return URL::temporarySignedRoute($namePrefix.'newsletter.conferma.show', now()->addDays(7), ['subscriber' => $this->id]);
    }

    /**
     * Registra la conferma e manda il contatto ad ActiveCampaign.
     * Idempotente: una seconda conferma non cambia la data della prima.
     */
    public function conferma(): bool
    {
        if ($this->haConfermato() && $this->isSubscribed()) {
            return false;
        }

        // Chi si era disiscritto rientra solo qui, al click: la richiesta
        // d'iscrizione da sola non cancella la disiscrizione, altrimenti
        // chiunque scrivendo l'indirizzo nel modulo cancellerebbe la prova
        // che il titolare era uscito dalla lista.
        $this->update(['confermato_il' => now(), 'unsubscribed_at' => null]);

        SyncNewsletterToActiveCampaign::dispatch($this);

        Log::channel('daily')->info('Conferma iscrizione newsletter', [
            'subscriber_id' => $this->id,
        ]);

        return true;
    }

    /**
     * Link di disiscrizione da mettere in fondo alle comunicazioni.
     *
     * È firmato e senza scadenza: una newsletter viene riletta anche a mesi
     * di distanza e il destinatario deve poter uscire dalla lista in quel
     * momento, non entro una finestra.
     */
    public function unsubscribeUrl(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (! in_array($locale, config('app.supported_locales', ['it']), true)) {
            $locale = 'it';
        }

        $namePrefix = $locale === 'it' ? '' : $locale.'.';

        return URL::signedRoute($namePrefix.'newsletter.unsubscribe.show', ['subscriber' => $this->id]);
    }

    /**
     * Registra la disiscrizione e la propaga ad ActiveCampaign.
     *
     * Idempotente: richiamarla su un iscritto già uscito non cambia la data
     * della prima disiscrizione né rimanda il contatto in coda.
     */
    public function unsubscribe(string $reason = 'self'): bool
    {
        if (! $this->isSubscribed()) {
            return false;
        }

        $this->update(['unsubscribed_at' => now()]);

        if ($this->ac_contact_id) {
            UnsubscribeNewsletterFromActiveCampaign::dispatch((int) $this->ac_contact_id, $this->email);
        }

        Log::channel('daily')->info('Disiscrizione newsletter', [
            'subscriber_id' => $this->id,
            'reason' => $reason,
        ]);

        return true;
    }
}
