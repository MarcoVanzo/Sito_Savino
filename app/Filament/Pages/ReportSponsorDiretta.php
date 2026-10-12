<?php

namespace App\Filament\Pages;

use App\Models\Game;
use App\Models\LiveAdStat;
use App\Models\LiveSession;
use App\Models\Sponsor;
use App\Services\Diretta\ReportDellaDiretta;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Il report degli sponsor della diretta, gara per gara, con l'esportazione
 * CSV da mandare allo sponsor. Solo contatori anonimi (`live_ad_stats`).
 */
class ReportSponsorDiretta extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Sponsor';

    protected static ?string $navigationLabel = 'Report diretta';

    protected static ?string $title = 'Report sponsor della diretta';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'report-diretta';

    protected static string $view = 'filament.pages.report-sponsor-diretta';

    public ?int $gara = null;

    public ?int $sponsor = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', LiveSession::class);
    }

    /** @return array<int, string> */
    public function gare(): array
    {
        return Game::query()
            ->with(['homeTeam', 'awayTeam'])
            ->whereIn('id', LiveAdStat::query()->select('game_id')->distinct())
            ->orderByDesc('match_date')
            ->get()
            ->mapWithKeys(fn (Game $g): array => [$g->id => $g->match_date->format('d/m/Y').' · '.$g->homeTeam?->nomePubblico().' – '.$g->awayTeam?->nomePubblico()])
            ->all();
    }

    /** @return array<int, string> */
    public function sponsorConDati(): array
    {
        return Sponsor::query()
            ->whereHas('liveCreatives', fn ($q) => $q->whereIn('id', LiveAdStat::query()->select('live_creative_id')))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function righe(): array
    {
        return app(ReportDellaDiretta::class)->righe($this->gara, $this->sponsor);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('esporta')
                ->label('Esporta CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): StreamedResponse {
                    $report = app(ReportDellaDiretta::class);
                    $csv = $report->csv($report->righe($this->gara, $this->sponsor));

                    return response()->streamDownload(fn () => print ($csv), 'report-sponsor-diretta-'.now()->format('Y-m-d').'.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
        ];
    }
}
