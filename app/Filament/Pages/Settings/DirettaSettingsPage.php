<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Resources\LiveCreativeResource;
use App\Observers\CacheInvalidationObserver;
use App\Support\Diretta\DirettaPerIlSito;
use App\Support\Diretta\ImpostazioniDellaDiretta as Diretta;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

/**
 * Interruttore generale e valori di partenza della diretta con sponsor.
 *
 * La usa la comunicazione, che cura gli sponsor: non serve essere
 * amministratori. Spento l'interruttore, il sito mostra la diretta come prima.
 * I valori del ciclo sono quelli con cui nasce la diretta di una gara: si
 * cambiano poi gara per gara nella scheda della partita.
 */
class DirettaSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Diretta e sponsor';

    protected static ?string $title = 'Diretta e sponsor';

    protected static ?string $navigationGroup = 'Sponsor';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'settings/diretta';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->role?->canManageSponsors();
    }

    public function mount(): void
    {
        parent::mount();

        // Le chiavi nude arrivano col valore grezzo ('0'), che per il
        // browser è una stringa piena, cioè un interruttore acceso.
        foreach (['diretta_sponsor_abilitata', 'diretta_ciclo_attivo'] as $chiave) {
            $this->data[$chiave] = filter_var($this->data[$chiave] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
    }

    /**
     * Home e calendario tengono in cache il pre-roll delle gare: accendere o
     * spegnere l'interruttore deve vedersi subito, come salvare una diretta.
     */
    public function save(): void
    {
        parent::save();

        app(CacheInvalidationObserver::class)->svuotaLeChiavi(DirettaPerIlSito::CHIAVI_DI_CACHE);
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
                Section::make('Creatività')
                    ->description('Gli spot e le cornici si caricano in Sponsor → Spot nella diretta, dove si scarica anche il modello grafico della cornice.')
                    ->schema([])
                    ->footerActions([
                        Action::make('creativita')
                            ->label('Vai agli spot')
                            ->url(fn (): string => LiveCreativeResource::getUrl('index')),
                    ]),
            ])
            ->statePath('data');
    }
}
