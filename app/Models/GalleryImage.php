<?php

namespace App\Models;

use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GalleryImage extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'gallery_event_id',
        'title',
        'category',
        'sort_order',
        'file_hash',
        'is_active',
        'needs_review',
        'ai_analyzed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'needs_review' => 'boolean',
        'sort_order' => 'integer',
        'ai_analyzed_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions($media);

        $this->addMediaConversion('lightbox')
            ->width(1600)
            ->quality(85)
            ->sharpen(10);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        // `id` come tiebreaker: sort_order non è univoco e senza di esso
        // l'ordine delle foto cambia a ogni query.
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function players()
    {
        return $this->morphedByMany(Player::class, 'person', 'gallery_image_person')
            ->withPivot('confidence_score')
            ->withTimestamps();
    }

    public function staffMembers()
    {
        return $this->morphedByMany(StaffMember::class, 'person', 'gallery_image_person')
            ->withPivot('confidence_score')
            ->withTimestamps();
    }

    /**
     * Ritorna tutte le persone (Player + StaffMember) associate a questa foto.
     */
    public function getAllPersonsAttribute(): Collection
    {
        return $this->players->concat($this->staffMembers);
    }

    public function galleryEvent()
    {
        return $this->belongsTo(GalleryEvent::class);
    }
}
