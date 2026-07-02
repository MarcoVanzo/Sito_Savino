<?php

namespace App\Models\Traits;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasOptimizedMedia
{
    /**
     * Register standard optimized conversions for web display.
     * Call this from registerMediaConversions() in each model.
     *
     * Conversions:
     * - thumb: 400×400, quality 80 — grids, admin previews, small cards
     * - card:  600px wide, quality 80 — medium cards (news, shop)
     */
    protected function registerStandardConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10)
            ->quality(80);

        $this->addMediaConversion('card')
            ->width(600)
            ->sharpen(10)
            ->quality(80);
    }
}
