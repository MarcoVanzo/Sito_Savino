<?php

namespace App\Models;

use App\Enums\TipoCreativita;
use App\Enums\UsoCreativita;
use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Database\Factories\LiveCreativeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * Una creatività sponsor della diretta: un video o un'immagine che va in onda
 * prima della diretta (pre-roll) o accanto al player durante la gara (cornice).
 *
 * Il file sta nella collezione `creativita` (Spaces in produzione); una
 * cornice può avere in più la fascia per il telefono (`fascia_mobile`). I
 * formati li controlla `FormatiDelleCreativita` al caricamento.
 */
class LiveCreative extends Model implements HasMedia
{
    /** @use HasFactory<LiveCreativeFactory> */
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity;

    public const COLLEZIONE_CONTENUTO = 'creativita';

    public const COLLEZIONE_FASCIA_MOBILE = 'fascia_mobile';

    protected $fillable = [
        'sponsor_id',
        'nome',
        'uso',
        'tipo',
        'durata_secondi',
        'link_url',
        'testo_alternativo',
        'attiva',
        'valida_dal',
        'valida_al',
    ];

    /** @var list<string> */
    public $translatable = ['testo_alternativo'];

    protected string $logLabelField = 'nome';

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'uso' => UsoCreativita::class,
            'tipo' => TipoCreativita::class,
            'durata_secondi' => 'integer',
            'attiva' => 'boolean',
            'valida_dal' => 'date',
            'valida_al' => 'date',
        ];
    }

    /** @return BelongsTo<Sponsor, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLEZIONE_CONTENUTO)->singleFile();
        $this->addMediaCollection(self::COLLEZIONE_FASCIA_MOBILE)->singleFile();
    }

    /**
     * Solo la miniatura per il pannello, e solo per le immagini: un video non
     * ha conversioni, e la cornice va in onda nel file originale (le misure
     * contano al pixel).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media !== null && ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $this->registerStandardConversions();
    }

    /**
     * Attive e dentro il periodo di validità nel giorno indicato (oggi).
     *
     * @param  Builder<LiveCreative>  $query
     * @return Builder<LiveCreative>
     */
    public function scopeValide(Builder $query, ?\DateTimeInterface $giorno = null): Builder
    {
        $giorno = ($giorno ?? now())->format('Y-m-d');

        return $query
            ->where('attiva', true)
            ->where(fn (Builder $q) => $q->whereNull('valida_dal')->orWhereDate('valida_dal', '<=', $giorno))
            ->where(fn (Builder $q) => $q->whereNull('valida_al')->orWhereDate('valida_al', '>=', $giorno))
            ->whereHas('media', fn (Builder $m) => $m->where('collection_name', self::COLLEZIONE_CONTENUTO));
    }

    public function valida(?\DateTimeInterface $giorno = null): bool
    {
        $giorno = ($giorno ?? now())->format('Y-m-d');

        return $this->attiva
            && ($this->valida_dal === null || $this->valida_dal->format('Y-m-d') <= $giorno)
            && ($this->valida_al === null || $this->valida_al->format('Y-m-d') >= $giorno)
            && $this->getFirstMedia(self::COLLEZIONE_CONTENUTO) !== null;
    }
}
