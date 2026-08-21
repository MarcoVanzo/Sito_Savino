<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * Il nome è tradotto perché finisce nei filtri pubblici delle news: sul sito
     * in inglese comparivano "Notizie", "Società", "Giovanile".
     *
     * @var list<string>
     */
    public $translatable = ['name'];

    protected $fillable = [
        'wp_id',
        'name',
        'slug',
        'sort_order',
        'description',
        'parent_id',
    ];

    /**
     * Post appartenenti a questa categoria.
     *
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Categoria genitore.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Sottocategorie figlie.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
