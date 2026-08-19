<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\RestrictsAccessByRole;
use App\Filament\Widgets\Analytics\SocialKpiWidget;
use App\Filament\Widgets\Analytics\SocialTrendWidget;
use App\Http\Controllers\Admin\MetaOAuthController;
use App\Models\SocialAccount;
use App\Services\Social\MetaOAuthService;
use App\Services\Social\SocialAnalyticsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

/**
 * Instagram e Facebook degli account Meta della società.
 *
 * Gli account collegati sono più d'uno (prima squadra, settore giovanile) e
 * arrivano tutti dallo stesso collegamento OAuth: la pagina lavora su quello
 * selezionato. Il collegamento vero e proprio non può stare qui — Meta rimanda
 * a un URL fisso — e passa da {@see MetaOAuthController}.
 */
class SocialAnalyticsPage extends Page
{
    use RestrictsAccessByRole;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationLabel = 'Social Analytics';

    protected static ?string $title = 'Social Analytics';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'analytics/social';

    protected static string $view = 'filament.pages.social-analytics';

    public ?int $accountId = null;

    public int $days = 28;

    public function mount(): void
    {
        $this->accountId = SocialAccount::query()->ordered()->value('id');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SocialKpiWidget::class,
            SocialTrendWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['accountId' => $this->accountId, 'days' => $this->days];
    }

    protected function getHeaderActions(): array
    {
        $accounts = SocialAccount::query()->ordered()->pluck('name', 'id');

        return array_values(array_filter([
            $accounts->count() > 1
                ? Action::make('account')
                    ->label('Account')
                    ->icon('heroicon-m-user-group')
                    ->form([
                        Select::make('accountId')
                            ->label('Account')
                            ->options($accounts)
                            ->default($this->accountId)
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->accountId = (int) $data['accountId'])
                : null,

            Action::make('period')
                ->label('Periodo')
                ->icon('heroicon-m-calendar-days')
                ->form([
                    Select::make('days')
                        ->label('Periodo')
                        ->options([
                            7 => 'Ultimi 7 giorni',
                            14 => 'Ultimi 14 giorni',
                            28 => 'Ultimi 28 giorni',
                            90 => 'Ultimi 90 giorni',
                        ])
                        ->default($this->days)
                        ->required(),
                ])
                ->action(fn (array $data) => $this->days = (int) $data['days']),

            Action::make('connect')
                ->label($accounts->isEmpty() ? 'Collega Meta' : 'Ricollega Meta')
                ->icon('heroicon-m-link')
                ->color($accounts->isEmpty() ? 'primary' : 'gray')
                // Un'azione e non un link. Il pannello gira in modalità SPA:
                // Livewire intercetta i click sui link interni e li carica via
                // fetch, ma questa rotta risponde con un redirect verso
                // facebook.com — cross-origin — quindi il fetch fallisce e non
                // succede assolutamente niente, senza nemmeno un errore a
                // schermo. Da un'azione, invece, il redirect lo esegue Livewire
                // cambiando window.location, e il giro OAuth parte davvero.
                ->action(fn () => redirect()->away(
                    app(MetaOAuthService::class)->authorizationUrl(auth()->id())
                )),

            Action::make('refresh')
                ->label('Aggiorna')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $this->forgetCache();

                    Notification::make()->title('Dati aggiornati da Meta')->success()->send();
                }),

            Action::make('disconnect')
                ->label('Scollega')
                ->icon('heroicon-m-x-mark')
                ->color('danger')
                ->visible(fn (): bool => $this->account() !== null)
                ->requiresConfirmation()
                ->modalDescription('Le analytics di questo account non saranno più aggiornate. La serie già scaricata resta in archivio.')
                ->action(function (): void {
                    $account = $this->account();

                    if ($account === null) {
                        return;
                    }

                    // Si azzera il token, non la riga: buttare via l'account
                    // porterebbe con sé la serie storica, che non si può riscaricare.
                    $account->forceFill([
                        'access_token' => null,
                        'token_expires_at' => null,
                    ])->save();

                    $this->forgetCache();

                    Notification::make()->title('Account scollegato')->success()->send();
                }),
        ]));
    }

    public function account(): ?SocialAccount
    {
        return $this->accountId === null ? null : SocialAccount::query()->find($this->accountId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function overview(): ?array
    {
        $account = $this->account();

        return $account === null ? null : app(SocialAnalyticsService::class)->overview($account, $this->days);
    }

    public function metaConfigured(): bool
    {
        return app(MetaOAuthService::class)->isConfigured();
    }

    public function redirectUri(): string
    {
        return app(MetaOAuthService::class)->redirectUri();
    }

    private function forgetCache(): void
    {
        $account = $this->account();

        if ($account === null) {
            return;
        }

        foreach (SocialAnalyticsService::ALLOWED_DAYS as $days) {
            Cache::forget("social:ig_totals:{$account->id}:{$days}");
            Cache::forget("social:ig_breakdowns:{$account->ig_account_id}:{$days}");
            Cache::forget("social:fb_page:{$account->page_id}:{$days}");
        }

        Cache::forget("social:ig_profile:{$account->ig_account_id}");
        Cache::forget("social:ig_demographics:{$account->ig_account_id}");
    }

    protected static function requiredAbility(): string
    {
        return 'canManageEditorial';
    }
}
