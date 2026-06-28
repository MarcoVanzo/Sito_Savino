<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class RecentActivityWidget extends Widget
{
    protected static string $view = 'filament.widgets.recent-activity';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 10;

    /**
     * Solo gli admin possono vedere questo widget.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && $user->role === UserRole::Admin;
    }

    public function getActivities(): Collection
    {
        return ActivityLog::with('user')
            ->latest('created_at')
            ->limit(10)
            ->get();
    }
}
