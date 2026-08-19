<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una giornata di insight Instagram, ricostruita una chiamata per volta.
 *
 * @property int $social_account_id
 * @property Carbon $day
 * @property bool $is_final
 */
class SocialInsightDaily extends Model
{
    protected $table = 'social_insights_daily';

    protected $fillable = [
        'social_account_id', 'ig_account_id', 'day', 'reach', 'views', 'follower_count',
        'likes', 'comments', 'shares', 'saves', 'reposts', 'replies',
        'total_interactions', 'accounts_engaged', 'profile_links_taps', 'is_final',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'is_final' => 'boolean',
        ];
    }

    /** @return BelongsTo<SocialAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
