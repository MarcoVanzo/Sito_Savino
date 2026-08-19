<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class RecentActivityWidget extends Widget
{
    protected static string $view = 'filament.widgets.recent-activity';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 10;

    // Disabilita il polling automatico
    protected static ?string $pollingInterval = null;

    /**
     * Solo gli admin possono vedere questo widget.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && $user->role === UserRole::SuperAdmin;
    }

    public function getActivities(): Collection
    {
        // In cache vanno solo gli id: serializzare i modelli Eloquent nella cache
        // su database produce un __PHP_Incomplete_Class alla rilettura e manda in
        // 500 tutta la dashboard.
        $ids = Cache::remember('filament:dashboard:recent_activity_ids', 120, function () {
            return ActivityLog::query()
                ->latest('created_at')
                ->limit(10)
                ->pluck('id')
                ->all();
        });

        if ($ids === []) {
            return ActivityLog::query()->whereRaw('1 = 0')->get();
        }

        return ActivityLog::with('user')
            ->whereIn('id', $ids)
            ->latest('created_at')
            ->get();
    }
}
