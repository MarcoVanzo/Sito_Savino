<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Actions\ImpostaLaDirettaAction;
use App\Filament\Resources\SponsorResource;
use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\SiteSetting;
use App\Observers\CacheInvalidationObserver;
use App\Services\Diretta\RegiaDellaDiretta;
use App\Support\Diretta\DirettaPerIlSito;
use App\Support\Diretta\ImpostazioniDellaDiretta as Diretta;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La diretta delle partite: l'elenco delle gare della società, dove per
 * ognuna si mette il link e si scelgono sponsor e grafiche («Imposta»), più
 * l'interruttore generale e i valori di partenza del ciclo.
 *
 * La usa la comunicazione, che cura gli sponsor: non serve essere
 * amministratori. Spento l'interruttore, il sito mostra la diretta come prima
 * (il link della partita vale comunque).
 */
class DirettaSettingsPage extends BaseSettingsPage implements HasTable
{
    use InteractsWithTable;

    /** Da quanti giorni indietro l'elenco mostra le partite. */
    private const GIORNI_INDIETRO = 2;

    protected static string $view = 'filament.pages.diretta-page';

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Diretta';

    protected static ?string $title = 'Diretta';

    protected static ?string $navigationGroup = 'Sponsor';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'settings/diretta';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->role?->canManageSponsors();
    }

    /**
     * Solo le impostazioni della diretta, non `parent::mount()`: quella
     * carica nel modulo tutte le impostazioni del sito, e lo stato di
     * Livewire finisce nella pagina. Qui entra anche la comunicazione, che
     * non deve vedere indirizzi degli avvisi e configurazioni interne.
     */
    public function mount(): void
    {
        $data = [];

        foreach (Diretta::PREDEFINITI as $chiave => $predefinito) {
            $data[$chiave] = SiteSetting::get($chiave, $predefinito);
        }

        // I valori salvati arrivano grezzi ('0'), che per il browser è una
        // stringa piena, cioè un interruttore acceso.
        foreach (['diretta_sponsor_abilitata', 'diretta_ciclo_attivo'] as $chiave) {
            $data[$chiave] = filter_var($data[$chiave], FILTER_VALIDATE_BOOLEAN);
        }

        $this->form->fill($data);
    }

    /**
     * Home e calendario tengono in cache il pre-roll delle gare: accendere o
     * spegnere l'interruttore deve vedersi subito, come salvare una diretta.
     * Chi ha già la diretta aperta lo sa dal JSON, che si ripubblica: spento
     * l'interruttore a gara in corso, gli sponsor spariscono anche a lui.
     */
    public function save(): void
    {
        $prima = Diretta::abilitata();

        parent::save();

        app(CacheInvalidationObserver::class)->svuotaLeChiavi(DirettaPerIlSito::CHIAVI_DI_CACHE);

        if (Diretta::abilitata() !== $prima) {
            app(RegiaDellaDiretta::class)->ripubblicaLeDiretteDaGiocare();
        }
    }

    protected function valoriPredefiniti(): array
    {
        return Diretta::PREDEFINITI;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Interruttore generale')
                    ->description('Spento, la diretta sul sito resta quella di sempre: niente spot prima né cornici durante la gara.')
                    ->schema([
                        Toggle::make('diretta_sponsor_abilitata')
                            ->label('Sponsor nella diretta'),
                    ]),
                Section::make('Prima della diretta (pre-roll)')
                    ->schema([
                        TextInput::make('diretta_preroll_salta_dopo_secondi')
                            ->label('Secondi prima di «Salta»')
                            ->helperText('0 = lo spot non si può saltare.')
                            ->numeric()->integer()->minValue(0)->maxValue(Diretta::PRIMA_DI_SALTA_MASSIMO)->required(),
                    ]),
                Section::make('Durante la gara')
                    ->description('Ogni tanto il player si riduce e accanto compare lo sponsor, poi torna grande. Funziona da solo, senza nessuno in regia.')
                    ->schema([
                        Toggle::make('diretta_ciclo_attivo')
                            ->label('Ciclo automatico')
                            ->columnSpanFull(),
                        TextInput::make('diretta_ciclo_ogni_minuti')
                            ->label('Ogni quanti minuti')
                            ->numeric()->integer()
                            ->minValue(Diretta::CICLO_OGNI_MINUTI_MINIMO)->maxValue(Diretta::CICLO_OGNI_MINUTI_MASSIMO)->required(),
                        TextInput::make('diretta_ciclo_durata_secondi')
                            ->label('Per quanti secondi')
                            ->numeric()->integer()
                            ->minValue(Diretta::CICLO_DURATA_MINIMA)->maxValue(Diretta::CICLO_DURATA_MASSIMA)->required(),
                        TextInput::make('diretta_ciclo_ritardo_minuti')
                            ->label('Minuti dopo l\'inizio della gara')
                            ->helperText('Per non partire sul riscaldamento.')
                            ->numeric()->integer()->minValue(0)->maxValue(Diretta::RITARDO_MASSIMO_MINUTI)->required(),
                        TextInput::make('diretta_pausa_durata_secondi')
                            ->label('Durata degli spot mandati dalla regia (secondi)')
                            ->numeric()->integer()
                            ->minValue(Diretta::PAUSA_DURATA_MINIMA)->maxValue(Diretta::PAUSA_DURATA_MASSIMA)->required(),
                    ])->columns(2),
                Section::make('Grafiche')
                    ->description('Spot e cornici si caricano nella scheda di ogni sponsor («Grafiche per la diretta»), dove si scarica anche il modello grafico della cornice.')
                    ->schema([])
                    ->footerActions([
                        Action::make('sponsor')
                            ->label('Vai agli sponsor')
                            ->url(fn (): string => SponsorResource::getUrl('index')),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Partite')
            ->description('Le gare della prima squadra (A1) da due giorni fa in avanti: «Imposta» per il link della diretta, gli sponsor e le loro grafiche.')
            ->query(
                Game::query()
                    ->with(['homeTeam', 'awayTeam', 'liveSession.creativitaDelPreroll.sponsor', 'liveSession.creativitaDellaGara.sponsor'])
                    ->dellaPrimaSquadra()
            )
            ->defaultSort('match_date')
            ->columns([
                Tables\Columns\TextColumn::make('match_date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('partita')
                    ->label('Partita')
                    ->getStateUsing(fn (Game $record): string => $record->homeTeam?->nomePubblico().' – '.$record->awayTeam?->nomePubblico())
                    ->description(fn (Game $record): string => (string) $record->competition_type?->getLabel()),
                Tables\Columns\IconColumn::make('con_link')
                    ->label('Link')
                    ->boolean()
                    ->getStateUsing(fn (Game $record): bool => filled($record->stream_url)),
                Tables\Columns\TextColumn::make('sponsor')
                    ->label('Sponsor')
                    ->getStateUsing(fn (Game $record): string => self::sponsorDellaGara($record))
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\Filter::make('prossime')
                    ->label('Solo da due giorni fa in avanti')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('match_date', '>=', now()->subDays(self::GIORNI_INDIETRO)->startOfDay())),
            ])
            ->actions([
                ImpostaLaDirettaAction::make(),
                // La diretta come la vedrà il tifoso, anche a interruttore spento.
                Tables\Actions\Action::make('prova')
                    ->label('Prova')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Game $record): string => route('diretta.prova', $record))
                    ->openUrlInNewTab(),
            ])
            ->paginated([10, 25, 50]);
    }

    /** I nomi degli sponsor con almeno una grafica nella diretta della gara. */
    private static function sponsorDellaGara(Game $game): string
    {
        $sessione = $game->liveSession;

        if ($sessione === null) {
            return '';
        }

        return $sessione->creativitaDelPreroll
            ->merge($sessione->creativitaDellaGara)
            ->map(fn (LiveCreative $c): string => (string) $c->sponsor?->name)
            ->unique()
            ->implode(', ');
    }
}
