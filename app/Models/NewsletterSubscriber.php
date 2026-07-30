<?php

namespace App\Models;

use App\Jobs\UnsubscribeNewsletterFromActiveCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'ip_address',
        'source',
        'synced_to_ac',
        'ac_contact_id',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_to_ac' => 'boolean',
            'subscribed_at' => 'datetime',
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

            Log::channel('daily')->info('Cancellazione iscritto newsletter', [
                'email' => $subscriber->email,
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

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
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
            'email' => $this->email,
            'reason' => $reason,
        ]);

        return true;
    }
}
