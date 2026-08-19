<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\AnalyticsSiteResource\Pages;
use App\Models\AnalyticsSite;
use App\Services\Analytics\WebAnalyticsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * I siti misurati su Google Analytics 4.
 *
 * Sono pochi e cambiano di rado, ma vanno gestiti dal pannello e non da una
 * variabile d'ambiente: la property si sbaglia, si rifà, si sostituisce, e ogni
 * volta servirebbe un rilascio. La serie storica resta comunque agganciata al
 * sito, non alla property.
 */
class AnalyticsSiteResource extends Resource
{
    protected static ?string $model = AnalyticsSite::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Sito Analytics';

    protected static ?string $pluralModelLabel = 'Siti Analytics';

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Siti Analytics';

    protected static ?string $navigationGroup = 'Amministrazione';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'analytics-sites';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::SuperAdmin;
    }

    public static function form(Form $form): Form
    {
        $email = app(WebAnalyticsService::class)->serviceAccountEmail();

        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->helperText('Come compare nel selettore della pagina Analytics Sito.')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('property_id')
                ->label('ID proprietà GA4')
                ->helperText(
                    'Solo il numero, da Google Analytics → Amministrazione → Impostazioni proprietà.'
                    .($email !== null ? ' Aggiungi '.$email.' come Visualizzatore sulla proprietà.' : '')
                )
                ->required()
                ->numeric()
                ->rule('regex:/^\d{1,20}$/')
                ->unique(ignoreRecord: true)
                ->maxLength(20),

            Forms\Components\TextInput::make('url')
                ->label('Indirizzo del sito')
                ->url()
                ->maxLength(255),

            Forms\Components\TextInput::make('sort')
                ->label('Ordine')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('property_id')->label('Proprietà GA4')->copyable(),
                Tables\Columns\TextColumn::make('url')->label('Indirizzo')->url(fn (AnalyticsSite $r): ?string => $r->url)->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('daily_count')->label('Giorni in archivio')->counts('daily'),
            ])
            ->actions([
                // Verificare l'accesso subito dopo aver inserito la property evita
                // il classico "la pagina è vuota" scoperto una settimana dopo.
                Tables\Actions\Action::make('verify')
                    ->label('Verifica accesso')
                    ->icon('heroicon-m-check-badge')
                    ->color('gray')
                    ->action(function (AnalyticsSite $record): void {
                        $result = app(WebAnalyticsService::class)->verify($record->property_id);

                        if ($result['ok']) {
                            Notification::make()
                                ->title('Accesso riuscito')
                                ->body('Utenti negli ultimi 7 giorni: '.($result['active_users_7d'] ?? 0))
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Accesso non riuscito')
                            ->body($result['message'] ?? '')
                            ->danger()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnalyticsSites::route('/'),
        ];
    }
}
