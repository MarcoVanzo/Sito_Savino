<?php

namespace App\Filament\Actions;

use App\Enums\UsoCreativita;
use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Observers\CacheInvalidationObserver;
use App\Services\Diretta\StatoPubblicoDellaDiretta;
use App\Support\Diretta\CicloDellaCornice;
use App\Support\Diretta\DirettaPerIlSito;
use App\Support\Diretta\ImpostazioniDellaDiretta as Diretta;
use App\Models\Sponsor;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\DB;

/**
 * «Imposta» sulle partite di Sponsor → Diretta: il link della diretta, gli
 * sponsor con le grafiche scelte (spot prima e cornici durante) e il ciclo
 * automatico, gara per gara.
 *
 * Sta nella pagina Diretta e non nel modulo della gara perché la gara la
 * modifica l'area sportiva, mentre la diretta la cura la comunicazione, che
 * della gara ha solo la vista (GamePolicy): qui scrive soltanto il link.
 */
class ImpostaLaDirettaAction
{
    public static function make(string $name = 'imposta'): Action
    {
        return Action::make($name)
            ->label('Imposta')
            ->icon('heroicon-o-play-circle')
            ->modalHeading(fn (Game $record): string => 'Diretta · '.$record->homeTeam?->nomePubblico().' – '.$record->awayTeam?->nomePubblico())
            ->modalDescription('Cosa vede chi guarda la diretta di questa gara dal sito. La diretta resta sempre visibile e comandabile: gli sponsor compaiono prima o accanto, mai sopra.')
            ->modalSubmitActionLabel('Salva')
            ->authorize(fn (): bool => (bool) auth()->user()?->can('update', LiveSession::class))
            ->fillForm(fn (Game $record): array => self::valori($record, LiveSession::perLaGaraSenzaCrearla($record)))
            ->form(fn (Game $record): array => self::campi($record))
            ->action(function (Game $record, array $data): void {
                $sessione = self::salva($record, $data);

                if (app(StatoPubblicoDellaDiretta::class)->pubblica($sessione)) {
                    Notification::make()->title('Diretta salvata')->success()->send();

                    return;
                }

                Notification::make()
                    ->title('Diretta salvata, ma il sito non l\'ha ancora ricevuta')
                    ->body('Lo stato non si è potuto scrivere sull\'archivio dei file: riprova fra poco. Intanto la diretta va senza sponsor durante la gara.')
                    ->warning()
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function salva(Game $game, array $data): LiveSession
    {
        return DB::transaction(function () use ($game, $data): LiveSession {
            if (array_key_exists('stream_url', $data)) {
                $game->update(['stream_url' => filled($data['stream_url']) ? trim((string) $data['stream_url']) : null]);
            }

            $sessione = LiveSession::perLaGara($game);
            [$preroll, $cornici] = self::grafiche($data['sponsor'] ?? []);

            $sessione->fill([
                'preroll_attivo' => (bool) ($data['preroll_attivo'] ?? false),
                // Nascosto con lo spot spento: resta il valore di prima.
                'preroll_salta_dopo_secondi' => (int) ($data['preroll_salta_dopo_secondi'] ?? $sessione->preroll_salta_dopo_secondi),
                'ciclo_attivo' => (bool) ($data['ciclo_attivo'] ?? false),
                'ciclo_ogni_minuti' => (int) $data['ciclo_ogni_minuti'],
                'ciclo_durata_secondi' => (int) $data['ciclo_durata_secondi'],
                'ciclo_ritardo_minuti' => (int) $data['ciclo_ritardo_minuti'],
                'pausa_durata_predefinita_secondi' => (int) $data['pausa_durata_predefinita_secondi'],
            ]);

            // L'inizio del ciclo si ricalcola solo se cambia il ritardo: un
            // «Riparti da adesso» della regia non va perso salvando altro.
            if ($sessione->isDirty('ciclo_ritardo_minuti') || $sessione->ciclo_inizio === null) {
                $sessione->ciclo_inizio = LiveSession::orariDelCiclo($game, $sessione->ciclo_ritardo_minuti)['ciclo_inizio'];
            }

            $sessione->ciclo_fine ??= LiveSession::orariDelCiclo($game, $sessione->ciclo_ritardo_minuti)['ciclo_fine'];
            $sessione->versione++;
            $sessione->save();

            $sessione->ordina('creativitaDelPreroll', $preroll);
            $sessione->ordina('creativitaDellaGara', $cornici);

            // Il pre-roll viaggia nelle pagine in cache di home e calendario.
            DB::afterCommit(fn () => app(CacheInvalidationObserver::class)->svuotaLeChiavi(DirettaPerIlSito::CHIAVI_DI_CACHE));

            return $sessione;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function valori(Game $game, LiveSession $sessione): array
    {
        return [
            'stream_url' => $game->stream_url,
            'preroll_attivo' => $sessione->preroll_attivo,
            'preroll_salta_dopo_secondi' => $sessione->preroll_salta_dopo_secondi,
            'sponsor' => $sessione->exists ? self::sponsorDellaSessione($sessione) : [],
            'ciclo_attivo' => $sessione->ciclo_attivo,
            'ciclo_ogni_minuti' => $sessione->ciclo_ogni_minuti,
            'ciclo_durata_secondi' => $sessione->ciclo_durata_secondi,
            'ciclo_ritardo_minuti' => $sessione->ciclo_ritardo_minuti,
            'pausa_durata_predefinita_secondi' => $sessione->pausa_durata_predefinita_secondi,
        ];
    }

    /**
     * Le grafiche della diretta raggruppate per sponsor, nell'ordine in cui
     * vanno in onda: è così che la redazione le sceglie nel modulo.
     *
     * @return list<array{sponsor_id: int, preroll: list<int>, cornici: list<int>}>
     */
    private static function sponsorDellaSessione(LiveSession $sessione): array
    {
        $gruppi = [];

        foreach (['preroll' => $sessione->creativitaDelPreroll()->get(), 'cornici' => $sessione->creativitaDellaGara()->get()] as $elenco => $creativita) {
            foreach ($creativita as $c) {
                $gruppi[$c->sponsor_id] ??= ['sponsor_id' => $c->sponsor_id, 'preroll' => [], 'cornici' => []];
                $gruppi[$c->sponsor_id][$elenco][] = $c->id;
            }
        }

        return array_values($gruppi);
    }

    /**
     * Dalle voci del modulo (sponsor e grafiche spuntate) ai due elenchi
     * ordinati: prima gli sponsor nell'ordine scelto, poi le loro grafiche.
     * Si tengono solo le grafiche che appartengono davvero a quello sponsor.
     *
     * @param  array<int|string, mixed>  $voci
     * @return array{0: list<int>, 1: list<int>}
     */
    private static function grafiche(array $voci): array
    {
        $preroll = [];
        $cornici = [];

        foreach ($voci as $voce) {
            $sponsor = (int) ($voce['sponsor_id'] ?? 0);
            $sue = LiveCreative::query()->where('sponsor_id', $sponsor)->pluck('uso', 'id');

            foreach (self::ids($voce['preroll'] ?? []) as $id) {
                if (($sue[$id] ?? null) === UsoCreativita::Preroll) {
                    $preroll[] = $id;
                }
            }

            foreach (self::ids($voce['cornici'] ?? []) as $id) {
                if (($sue[$id] ?? null) === UsoCreativita::Cornice) {
                    $cornici[] = $id;
                }
            }
        }

        return [$preroll, $cornici];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private static function campi(Game $game): array
    {
        return [
            Forms\Components\Section::make('Link della diretta')
                ->schema([
                    Forms\Components\TextInput::make('stream_url')
                        ->hiddenLabel()
                        ->url()
                        // Finisce in un `href` e, se incorporabile, in un `src`:
                        // solo indirizzi web.
                        ->rule('url:http,https')
                        ->maxLength(500)
                        ->placeholder('es. https://www.youtube.com/watch?v=...')
                        ->helperText('Con un link YouTube, Vimeo, Twitch o Dailymotion la diretta si apre in una finestra dentro al sito. Altri indirizzi si aprono in una scheda nuova.'),
                ]),
            Forms\Components\Section::make('Sponsor')
                ->description(fn (): string => Diretta::abilitata()
                    ? 'Per ogni sponsor spunta le grafiche da mandare in onda. Gli spot ruotano prima della diretta (uno per visione), le cornici durante la gara, nell\'ordine degli sponsor.'
                    : 'Gli sponsor nella diretta sono spenti (interruttore qui sotto): puoi già prepararli, il sito li mostrerà quando li accendi.')
                ->schema([
                    Forms\Components\Repeater::make('sponsor')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\Select::make('sponsor_id')
                                ->label('Sponsor')
                                ->options(fn (): array => Sponsor::query()->whereHas('liveCreatives', fn ($q) => $q->where('attiva', true))->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required()
                                ->distinct()
                                ->live()
                                ->helperText('Compaiono gli sponsor con almeno una grafica: si caricano nella scheda dello sponsor.'),
                            Forms\Components\CheckboxList::make('preroll')
                                ->label('Spot prima della diretta')
                                ->options(fn (Get $get): array => self::graficheDelloSponsor((int) $get('sponsor_id'), UsoCreativita::Preroll))
                                ->visible(fn (Get $get): bool => self::graficheDelloSponsor((int) $get('sponsor_id'), UsoCreativita::Preroll) !== []),
                            Forms\Components\CheckboxList::make('cornici')
                                ->label('Cornici durante la gara')
                                ->options(fn (Get $get): array => self::graficheDelloSponsor((int) $get('sponsor_id'), UsoCreativita::Cornice))
                                ->visible(fn (Get $get): bool => self::graficheDelloSponsor((int) $get('sponsor_id'), UsoCreativita::Cornice) !== []),
                        ])
                        ->itemLabel(fn (array $state): ?string => Sponsor::query()->whereKey($state['sponsor_id'] ?? 0)->value('name'))
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Aggiungi uno sponsor'),
                    Forms\Components\Toggle::make('preroll_attivo')
                        ->label('Spot prima della diretta')
                        ->live(),
                    Forms\Components\TextInput::make('preroll_salta_dopo_secondi')
                        ->label('Secondi prima di «Salta»')
                        ->helperText('0 = non si può saltare.')
                        ->numeric()->integer()->minValue(0)->maxValue(Diretta::PRIMA_DI_SALTA_MASSIMO)->required()
                        ->visible(fn (Get $get): bool => (bool) $get('preroll_attivo')),
                ]),
            Forms\Components\Section::make('Durante la gara')
                ->description('Ogni tanto il player si riduce e accanto compare la cornice dello sponsor, poi torna grande. Le stesse cornici ruotano negli spot mandati dalla regia.')
                ->schema([
                    Forms\Components\Toggle::make('ciclo_attivo')
                        ->label('Ciclo automatico')
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('ciclo_ogni_minuti')
                        ->label('Ogni quanti minuti')
                        ->numeric()->integer()->minValue(Diretta::CICLO_OGNI_MINUTI_MINIMO)->maxValue(Diretta::CICLO_OGNI_MINUTI_MASSIMO)->required()
                        ->live(onBlur: true),
                    Forms\Components\TextInput::make('ciclo_durata_secondi')
                        ->label('Per quanti secondi')
                        ->numeric()->integer()->minValue(Diretta::CICLO_DURATA_MINIMA)->maxValue(Diretta::CICLO_DURATA_MASSIMA)->required(),
                    Forms\Components\TextInput::make('ciclo_ritardo_minuti')
                        ->label('Minuti dopo l\'inizio della gara')
                        ->numeric()->integer()->minValue(0)->maxValue(Diretta::RITARDO_MASSIMO_MINUTI)->required()
                        ->live(onBlur: true),
                    Forms\Components\TextInput::make('pausa_durata_predefinita_secondi')
                        ->label('Durata degli spot della regia (secondi)')
                        ->numeric()->integer()->minValue(Diretta::PAUSA_DURATA_MINIMA)->maxValue(Diretta::PAUSA_DURATA_MASSIMA)->required(),
                    Forms\Components\Placeholder::make('anteprima_del_ciclo')
                        ->label('Primi cicli')
                        ->content(fn (Get $get): string => self::anteprima($game, $get))
                        ->columnSpanFull(),
                ])->columns(2),
        ];
    }

    /**
     * Le grafiche attive dello sponsor per un uso, come opzioni da spuntare.
     *
     * @return array<int, string>
     */
    private static function graficheDelloSponsor(int $sponsor, UsoCreativita $uso): array
    {
        if ($sponsor === 0) {
            return [];
        }

        return LiveCreative::query()
            ->where('sponsor_id', $sponsor)
            ->where('uso', $uso)
            ->where('attiva', true)
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (LiveCreative $c): array => [$c->id => $c->nome.' ('.$c->tipo->getLabel().', '.$c->durata_secondi.' s)'])
            ->all();
    }

    private static function anteprima(Game $game, Get $get): string
    {
        if (! $get('ciclo_attivo')) {
            return 'Ciclo spento: la cornice compare solo quando la manda la regia.';
        }

        $ogni = (int) $get('ciclo_ogni_minuti');
        $orari = LiveSession::orariDelCiclo($game, (int) $get('ciclo_ritardo_minuti'));
        $istanti = CicloDellaCornice::primiIstanti($orari['ciclo_inizio'], $orari['ciclo_fine'], $ogni);

        if ($istanti === []) {
            return 'Nessun ciclo con questi valori.';
        }

        return 'Alle '.implode(', ', array_map(fn ($istante) => $istante->format('H:i'), $istanti))
            .', poi ogni '.$ogni.' minuti fino alle '.$orari['ciclo_fine']->format('H:i').'.';
    }

    /**
     * @param  array<int|string, mixed>  $voci
     * @return list<int>
     */
    private static function ids(array $voci): array
    {
        return array_values(array_map('intval', array_filter($voci, 'is_numeric')));
    }
}
