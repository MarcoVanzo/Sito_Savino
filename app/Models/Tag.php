<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\LogsActivity;

class Tag extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Post associati a questo tag.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
