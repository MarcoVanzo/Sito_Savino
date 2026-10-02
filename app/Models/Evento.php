<?php

namespace App\Models;

use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * Un appuntamento dello spazio «Eventi» della homepage.
 *
 * Non è una gara: quelle arrivano dalla Lega e dalla CEV. Qui stanno
 * presentazioni, feste, iniziative con i tifosi, che la redazione inserisce
 * dal pannello.
 */
class Evento extends Model implements HasMedia
{
    use HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity;

    public const COLLEZIONE_COPERTINA = 'copertina';

    protected $table = 'eventi';

    protected $fillable = [
        'titolo',
        'descrizione',
        'luogo',
        'inizia_il',
        'finisce_il',
        'link',
        'pubblicato',
    ];

    /** @var list<string> */
    public $translatable = ['titolo', 'descrizione'];

    protected $casts = [
        'inizia_il' => 'datetime',
        'finisce_il' => 'datetime',
        'pubblicato' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLEZIONE_COPERTINA)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();
    }

    /**
     * Pubblicati e non ancora finiti, dal più vicino.
     *
     * Un evento senza fine resta in pagina per tutta la giornata in cui
     * comincia: una festa alle 18 non deve sparire alle 18:01.
     *
     * @param  Builder<Evento>  $query
     * @return Builder<Evento>
     */
    public function scopeInArrivo(Builder $query): Builder
    {
        return $query
            ->where('pubblicato', true)
            ->where(function (Builder $q) {
                $q->where('finisce_il', '>=', now())
                    ->orWhere(fn (Builder $senzaFine) => $senzaFine
                        ->whereNull('finisce_il')
                        ->where('inizia_il', '>=', now()->startOfDay()));
            })
            ->orderBy('inizia_il');
    }

    /**
     * Quello che la homepage riceve di un evento.
     *
     * @return array{id: int, titolo: string, descrizione: string, luogo: ?string, inizia_il: string, finisce_il: ?string, link: ?string, immagine: string}
     */
    public function perLaHome(): array
    {
        return [
            'id' => $this->id,
            'titolo' => (string) $this->getTranslation('titolo', app()->getLocale()),
            'descrizione' => (string) $this->getTranslation('descrizione', app()->getLocale()),
            'luogo' => $this->luogo,
            'inizia_il' => $this->inizia_il->toIso8601String(),
            'finisce_il' => $this->finisce_il?->toIso8601String(),
            'link' => $this->link,
            'immagine' => $this->indirizzoDellaCopertina(),
        ];
    }

    /**
     * Come per gli sponsor: la conversione ridotta solo se è già stata
     * generata, o per il tempo della coda il riquadro resta vuoto.
     */
    private function indirizzoDellaCopertina(): string
    {
        $copertina = $this->getFirstMedia(self::COLLEZIONE_COPERTINA);

        if ($copertina === null) {
            return '';
        }

        return $copertina->hasGeneratedConversion('card') ? $copertina->getUrl('card') : $copertina->getUrl();
    }
}
