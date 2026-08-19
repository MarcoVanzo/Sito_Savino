<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una giornata di traffico di un sito, come l'ha riportata GA4.
 *
 * @property int $analytics_site_id
 * @property Carbon $day
 * @property bool $is_final
 */
class WebAnalyticsDaily extends Model
{
    protected $table = 'web_analytics_daily';

    protected $fillable = [
        'analytics_site_id', 'property_id', 'day', 'active_users', 'new_users',
        'sessions', 'page_views', 'engaged_sessions', 'engagement_seconds', 'is_final',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'is_final' => 'boolean',
        ];
    }

    /** @return BelongsTo<AnalyticsSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSite::class, 'analytics_site_id');
    }
}
