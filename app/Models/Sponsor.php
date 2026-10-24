<?php

namespace App\Models;

use App\Enums\SponsorTier;
use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Sponsor extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'name', 'url', 'tier', 'sort_order', 'solo_diretta', 'testo_alternativo',
    ];

    // La tabella `sponsors` non ha una colonna `description`.
    public $translatable = [];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tier' => SponsorTier::class,
            'solo_diretta' => 'boolean',
        ];
    }

    /**
     * Le grafiche della diretta se ne vanno una per una, non con la cascata
     * del database: così si cancellano i loro file su Spaces, le dirette
     * pubblicate si riscrivono senza di loro e il report ne tiene i nomi.
     */
    protected static function booted(): void
    {
        static::deleting(function (Sponsor $sponsor): void {
            $sponsor->liveCreatives()->get()->each->delete();
        });
    }

    /**
     * Gli spot e le cornici della diretta.
     *
     * @return HasMany<LiveCreative, $this>
     */
    public function liveCreatives(): HasMany
    {
        return $this->hasMany(LiveCreative::class);
    }

    /**
     * Il testo alternativo del logo: quello scritto dalla redazione, altrimenti
     * il nome dello sponsor (un logo dice chi e', non com'e' fatto).
     */
    public function testoAlternativoDelLogo(): string
    {
        return filled($this->testo_alternativo) ? trim($this->testo_alternativo) : (string) $this->name;
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();
    }
}
