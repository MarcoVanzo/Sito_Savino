<?php

namespace App\Models;

use App\Enums\StaffType;
use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class StaffMember extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'first_name',
        'last_name',
        'role',
        'type',
        'section',
        'sort_order',
    ];

    public $translatable = ['role', 'biography'];

    protected $casts = [
        'type' => StaffType::class,
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions($media);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('staff')
            ->singleFile();
    }

    public function galleryImages()
    {
        return $this->morphToMany(GalleryImage::class, 'person', 'gallery_image_person')
            ->withPivot('confidence_score')
            ->withTimestamps();
    }
}
