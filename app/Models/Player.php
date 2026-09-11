<?php

namespace App\Models;

use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Player extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'date_of_birth',
        'nationality', 'instagram_handle', 'lega_volley_id',
        'ai_face_examples',
        'wikipedia_title', 'wikipedia_lang', 'wikipedia_revid', 'palmares_synced_at',
    ];

    // Nessun campo traducibile sulla tabella `players`: `role` e `biography`
    // vivono rispettivamente su `rosters` e `staff_members`.
    public $translatable = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'ai_face_examples' => 'integer',
        'wikipedia_revid' => 'integer',
        'palmares_synced_at' => 'datetime',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Indirizzo del profilo Instagram, o null se non ce n'è uno valido.
     */
    public function instagramUrl(): ?string
    {
        $handle = self::instagramHandleDa($this->instagram_handle);

        return $handle !== null ? "https://www.instagram.com/{$handle}/" : null;
    }

    /**
     * Il nome utente da quello che la redazione scrive: "@nome", "nome" oppure
     * il link del profilo copiato dall'app, che si porta dietro `?utm_source=…`.
     */
    public static function instagramHandleDa(?string $valore): ?string
    {
        $valore = trim((string) $valore);

        if (preg_match('~instagram\.com/([A-Za-z0-9._]+)~i', $valore, $trovato)) {
            $valore = $trovato[1];
        }

        $valore = ltrim($valore, '@');

        return preg_match('/^[A-Za-z0-9._]{1,30}$/', $valore) ? $valore : null;
    }

    public function rosters()
    {
        return $this->hasMany(Roster::class);
    }

    public function stats()
    {
        return $this->hasMany(PlayerStat::class);
    }

    /**
     * Palmarès: trofei di club, medaglie in nazionale e premi individuali.
     *
     * L'ordinamento è quello di pubblicazione — categoria, poi dal più recente:
     * il banner pubblico non riordina, mostra quello che riceve.
     *
     * @return HasMany<PlayerHonour, $this>
     */
    public function honours(): HasMany
    {
        return $this->hasMany(PlayerHonour::class)
            ->orderBy('sort_order')
            ->orderByDesc('year')
            ->orderBy('id');
    }

    /**
     * @return MorphToMany<GalleryImage, $this>
     */
    public function galleryImages(): MorphToMany
    {
        return $this->morphToMany(GalleryImage::class, 'person', 'gallery_image_person')
            ->withPivot('confidence_score')
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('players')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();
    }
}
