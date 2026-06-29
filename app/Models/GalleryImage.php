<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class GalleryImage extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'gallery_event_id',
        'title',
        'category',
        'sort_order',
        'is_active',
        'needs_review',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'needs_review' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')->singleFile();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'gallery_image_player')
            ->withPivot('confidence_score')
            ->withTimestamps();
    }

    public function galleryEvent()
    {
        return $this->belongsTo(GalleryEvent::class);
    }
}
