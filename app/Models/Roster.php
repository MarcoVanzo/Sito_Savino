<?php

namespace App\Models;

use App\Enums\PlayerPosition;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Roster extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'player_id', 'team_id', 'season_id',
        'jersey_number', 'role', 'height_cm', 'is_captain',
        'bio',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
        'height_cm' => 'integer',
        'role' => PlayerPosition::class,
    ];

    protected $appends = ['official_photo_url'];

    /**
     * URL della foto ufficiale (collection Spatie 'rosters_official').
     */
    public function getOfficialPhotoUrlAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('rosters_official');

        return $url !== '' ? $url : null;
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
