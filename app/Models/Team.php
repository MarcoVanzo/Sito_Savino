<?php

namespace App\Models;

use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Team extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'category', 'is_internal', 'lvf_club_id', 'logo_url',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'lvf_club_id' => 'integer',
    ];

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }

    /**
     * Tutti gli identificativi che la Lega ha assegnato a questa società: ne
     * cambia uno per stagione.
     */
    public function lvfClubIds(): HasMany
    {
        return $this->hasMany(TeamLvfClubId::class);
    }

    public function rosters()
    {
        return $this->hasMany(Roster::class);
    }

    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }

    /**
     * Collezione del logo caricato manualmente dal CMS.
     */
    public const LOGO_CUSTOM = 'logo';

    /**
     * Collezione del logo scaricato dal sito della Lega.
     */
    public const LOGO_IMPORTED = 'logo-lvf';

    public function registerMediaCollections(): void
    {
        // Due collezioni distinte, non una con un flag: la sincronizzazione
        // scrive solo su `logo-lvf` e il CMS solo su `logo`, quindi un logo
        // caricato a mano non può essere sovrascritto dall'import successivo.
        $this->addMediaCollection(self::LOGO_CUSTOM)->singleFile();
        $this->addMediaCollection(self::LOGO_IMPORTED)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions($media);
    }

    /**
     * Logo da mostrare, in ordine di precedenza: quello caricato dal CMS, poi
     * quello importato dalla Lega, infine l'URL remoto come ultima risorsa.
     */
    public function logoUrl(): ?string
    {
        foreach ([self::LOGO_CUSTOM, self::LOGO_IMPORTED] as $collection) {
            $url = $this->getFirstMediaUrl($collection);

            if ($url !== '') {
                return $url;
            }
        }

        return $this->logo_url;
    }

    public function hasCustomLogo(): bool
    {
        return $this->getFirstMedia(self::LOGO_CUSTOM) !== null;
    }
}
