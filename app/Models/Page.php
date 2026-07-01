<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Page extends Model implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia, LogsActivity;

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
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10);
    }
}

