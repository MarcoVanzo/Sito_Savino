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

    public $translatable = ['role'];

    protected $casts = [
        'type' => StaffType::class,
    ];

    protected $appends = ['full_name'];

    /**
     * Un membro nuovo va in fondo. Il modulo non chiede la posizione e la
     * colonna parte da 0, cioè prima di tutti: Team Manager e Logistic
     * Manager erano finiti in cima all'organigramma, sopra il Presidente.
     */
    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            if (! $member->sort_order) {
                $member->sort_order = (int) static::query()->max('sort_order') + 1;
            }
        });
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();
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
