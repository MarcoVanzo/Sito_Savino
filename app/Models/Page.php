<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Page extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity {
        HasTranslations::getTranslation as protected spatieGetTranslation;
    }

    /**
     * Recupera le colonne translatable rimaste in testo semplice.
     *
     * Le pagine importate da WordPress possono avere `title`, `content` o
     * `excerpt` salvati come testo e non come JSON `{"it":…,"en":…}`. Spatie non
     * riconosce quel formato e restituisce stringa vuota: nel CMS il campo
     * appare vuoto e un salvataggio cancellerebbe il testo che il sito sta
     * pubblicando. Restituendo il valore grezzo l'editor mostra ciò che c'è
     * davvero, e al primo salvataggio la colonna passa da sola al formato JSON.
     */
    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): mixed
    {
        $value = $this->spatieGetTranslation($key, $locale, $useFallbackLocale);

        if ($value !== '' && $value !== null && $value !== []) {
            return $value;
        }

        $raw = $this->getAttributes()[$key] ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return $value;
        }

        json_decode($raw, true);

        // Un JSON valido è già gestito da spatie: qui interessa solo il testo
        // semplice rimasto dalle importazioni.
        return json_last_error() === JSON_ERROR_NONE ? $value : $raw;
    }

    protected $fillable = [
        'wp_id', 'title', 'slug', 'template', 'content', 'content_data', 'excerpt',
        'status', 'author_id', 'parent_id',
        'meta_title', 'meta_description', 'meta_keywords',
    ];

    public $translatable = ['title', 'content', 'excerpt', 'content_data', 'meta_description'];

    protected $casts = [
        'status' => PostStatus::class,
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', PostStatus::Published);
    }

    /**
     * Quando il model viene serializzato (es. via Inertia), i campi translatable
     * vengono risolti nella lingua corrente anziché restituire l'array completo.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        foreach ($this->translatable as $field) {
            if (isset($array[$field])) {
                $array[$field] = $this->getTranslation($field, app()->getLocale());
            }
        }

        return $array;
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();
    }

    /**
     * Foto della galleria di pagina (collection `gallery`), usata dalle pagine
     * di contenuto — per esempio Ospitalità — e gestita dal pannello.
     *
     * @return list<array{url: string, thumb: string, name: string}>
     */
    public function getGalleryImagesAttribute(): array
    {
        return $this->getMedia('gallery')
            ->map(fn (Media $media) => [
                'url' => $media->getUrl(),
                'thumb' => $media->hasGeneratedConversion('card') ? $media->getUrl('card') : $media->getUrl(),
                'name' => $media->getCustomProperty('caption') ?: $media->name,
            ])
            ->values()
            ->all();
    }

    /**
     * URL dell'immagine di copertina (collection `cover`), usata come hero
     * dai template pubblici. Restituisce null se non è stata caricata.
     */
    public function getCoverUrlAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('cover', 'card') ?: $this->getFirstMediaUrl('cover');

        return $url !== '' ? $url : null;
    }
}
