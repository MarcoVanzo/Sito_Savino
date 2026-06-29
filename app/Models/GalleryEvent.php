<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryEvent extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        'event_date',
        'description',
        'category',
        'is_active',
    ];

    protected $casts = [
        'event_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function galleryImages()
    {
        return $this->hasMany(GalleryImage::class);
    }
}
