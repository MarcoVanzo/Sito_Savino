<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class GalleryEvent extends Model
{
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * Il titolo è tradotto perché è l'intestazione dell'album nella gallery
     * pubblica. I titoli generati da `gallery:create-from-posts` contengono il
     * nome del mese, che va scritto nelle due lingue al momento della creazione.
     *
     * @var list<string>
     */
    public $translatable = ['title'];

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
