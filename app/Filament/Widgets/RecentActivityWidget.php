<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Widgets\Widget;

class RecentActivityWidget extends Widget
{
    protected static string $view = 'filament.widgets.recent-activity';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 10;

    /**
     * Solo gli admin possono vedere questo widget.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && $user->role === \App\Enums\UserRole::Admin;
    }

    public function getActivities(): \Illuminate\Database\Eloquent\Collection
    {
        return ActivityLog::with('user')
            ->latest('created_at')
            ->limit(10)
            ->get();
    }
}
