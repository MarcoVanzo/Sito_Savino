<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use App\Models\Traits\TestoTradotto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Post extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity, TestoTradotto;

    protected $fillable = [
        'wp_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'status',
        'published_at',
        'author_id',
        'meta_title',
        'meta_description',
        'testo_alternativo',
    ];

    public $translatable = ['title', 'content', 'excerpt', 'meta_description', 'testo_alternativo'];

    /**
     * Lo stato della traduzione automatica (TraduciLaNotiziaInInglese) è
     * contabilità del job: nel registro bastano i testi inglesi che scrive.
     *
     * @var list<string>
     */
    protected array $logExclude = ['traduzioni_automatiche'];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'status' => PostStatus::class,
            'traduzioni_automatiche' => 'array',
        ];
    }

    /**
     * Se la notizia esiste davvero in questa lingua: titolo scritto e, se in
     * italiano c'e' un testo, scritto anche quello. Senza, `/en/news/…` mostra
     * l'italiano e Google la scarta come doppione della pagina italiana
     * («canonica diversa da quella specificata», Search Console 26/10/2026):
     * niente hreflang, sitemap ne' indicizzazione in quella lingua, come per
     * i prodotti (Product::tradottoIn).
     */
    public function tradottoIn(string $locale): bool
    {
        if ($locale === config('app.fallback_locale')) {
            return true;
        }

        if (trim((string) $this->getTranslation('title', $locale, false)) === '') {
            return false;
        }

        $testo = fn (string $lingua): string => trim(strip_tags((string) $this->getTranslation('content', $lingua, false)));

        return $testo((string) config('app.fallback_locale')) === '' || $testo($locale) !== '';
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query
            ->where('status', PostStatus::Published)
            ->where(fn ($q) => $q
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now())
            );
    }

    /**
     * Il testo alternativo della copertina nella lingua della pagina: quello
     * scritto dalla redazione, altrimenti il titolo della notizia. Con il
     * ripiego sull'italiano, come il titolo nelle schede.
     */
    public function testoAlternativoDellaCopertina(?string $lingua = null): string
    {
        $lingua ??= app()->getLocale();
        $scritto = trim((string) $this->getTranslation('testo_alternativo', $lingua));

        return $scritto !== '' ? $scritto : trim((string) $this->getTranslation('title', $lingua));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();

        $this->addMediaConversion('detail')
            ->width(1200)
            ->quality(85)
            ->sharpen(10);
    }
}
