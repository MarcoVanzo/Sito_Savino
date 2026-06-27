<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\LogsActivity;

class ProductCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name', 'slug', 'description'
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
