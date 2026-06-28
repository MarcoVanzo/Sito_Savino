<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class WelcomeHeaderWidget extends Widget
{
    protected static string $view = 'filament.widgets.welcome-header-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    protected function getViewData(): array
    {
        $hour = now()->hour;
        $greeting = 'Buongiorno';

        if ($hour >= 14 && $hour < 18) {
            $greeting = 'Buon pomeriggio';
        } elseif ($hour >= 18) {
            $greeting = 'Buonasera';
        }

        return [
            'greeting' => $greeting,
            'user' => auth()->user(),
        ];
    }
}
