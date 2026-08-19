<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un sito misurato su Google Analytics 4.
 *
 * @property int $id
 * @property string $name
 * @property string $property_id
 * @property string|null $url
 * @property int $sort
 */
class AnalyticsSite extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'property_id', 'url', 'sort'];

    /** @return HasMany<WebAnalyticsDaily, $this> */
    public function daily(): HasMany
    {
        return $this->hasMany(WebAnalyticsDaily::class);
    }

    /**
     * @param  Builder<AnalyticsSite>  $query
     * @return Builder<AnalyticsSite>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }
}
