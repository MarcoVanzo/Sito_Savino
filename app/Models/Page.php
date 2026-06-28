<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity;

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
}
