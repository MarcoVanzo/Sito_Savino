<?php

namespace App\Filament\Actions;

use App\Enums\UsoCreativita;
use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Models\Sponsor;
use App\Observers\CacheInvalidationObserver;
use App\Services\Diretta\RegiaDellaDiretta;
use App\Support\Diretta\CicloDellaCornice;
use App\Support\Diretta\DirettaPerIlSito;
use App\Support\Diretta\ImpostazioniDellaDiretta as Diretta;
use App\Support\LiveStream;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

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
            ->schema(fn (Game $record): array => self::campi($record))
            ->action(function (Game $record, array $data): void {
                if (self::salva($record, $data)) {
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
     * Salva la diretta della gara passando dalla regia (lock, versione,
     * JSON pubblicato dalla riga bloccata): salvata insieme a un pulsante
     * della regia, una delle due andava persa per chi guarda.
     *
     * Restituisce se il JSON del sito è stato scritto.
     *
     * @param  array<string, mixed>  $data
     */
    public static function salva(Game $game, array $data): bool
    {
        [$preroll, $cornici] = self::grafiche($data['sponsor'] ?? []);

        // Il link della gara si scrive dentro la transazione di `cambia()`:
        // con una transazione esterna il JSON partiva prima del suo commit, e
        // se quel commit falliva il browser aveva già una versione che il
        // database non aveva più (e scartava la successiva con lo stesso numero).
        $pubblicato = app(RegiaDellaDiretta::class)->cambia(
            LiveSession::perLaGara($game),
            function (LiveSession $sessione) use ($game, $data, $preroll, $cornici): void {
                if (array_key_exists('stream_url', $data)) {
                    $game->update(['stream_url' => filled($data['stream_url']) ? trim((string) $data['stream_url']) : null]);
                }

                self::applica($sessione, $game, $data, $preroll, $cornici);
            },
        );

        // Il pre-roll viaggia nelle pagine in cache di home e calendario.
        app(CacheInvalidationObserver::class)->svuotaLeChiavi(DirettaPerIlSito::CHIAVI_DI_CACHE);

        return $pubblicato;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $preroll
     * @param  list<int>  $cornici
     */
    private static function applica(LiveSession $sessione, Game $game, array $data, array $preroll, array $cornici): void
    {
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

        $sessione->ordina('creativitaDelPreroll', $preroll);
        $sessione->ordina('creativitaDellaGara', $cornici);
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
     * @return list<Component>
     */
    private static function campi(Game $game): array
    {
        return [
            Section::make('Link della diretta')
                ->schema([
                    TextInput::make('stream_url')
                        ->hiddenLabel()
                        ->url()
                        // Finisce in un `href` e, se incorporabile, in un `src`:
                        // solo indirizzi web.
                        ->rule('url:http,https')
                        // Il tifoso resta sul sito: si accettano solo i link
                        // che il sito sa mostrare nella sua finestra. Il link
                        // del canale (`/@canale/live`) non è un video.
                        ->rule(fn (): Closure => function (string $attribute, mixed $valore, Closure $fail): void {
                            if (filled($valore) && LiveStream::embedUrl((string) $valore) === null) {
                                $fail('Questo link non si può mostrare dentro il sito. Usa il link della singola diretta (YouTube: Condividi → Copia link, es. https://youtube.com/live/…), non quello del canale.');
                            }
                        })
                        ->maxLength(500)
                        ->placeholder('es. https://youtube.com/live/AbC123xyz')
                        ->helperText('Il link della singola diretta: YouTube, Vimeo, Twitch o Dailymotion. Su YouTube: programma la diretta, poi Condividi → Copia link; la diretta deve consentire l\'incorporamento.'),
                ]),
            Section::make('Sponsor')
                ->description(fn (): string => Diretta::abilitata()
                    ? 'Per ogni sponsor spunta le grafiche da mandare in onda. Gli spot ruotano prima della diretta (uno per visione), le cornici durante la gara, nell\'ordine degli sponsor.'
                    : 'Gli sponsor nella diretta sono spenti (interruttore qui sotto): puoi già prepararli, il sito li mostrerà quando li accendi.')
                ->schema([
                    Repeater::make('sponsor')
                        ->hiddenLabel()
                        ->schema([
                            Select::make('sponsor_id')
                                ->label('Sponsor')
                                // Anche quelli con le grafiche tutte spente: uno già
                                // scelto per la gara non deve diventare un valore
                                // non ammesso che blocca il salvataggio.
                                ->options(fn (): array => Sponsor::query()->whereHas('liveCreatives')->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required()
                                ->distinct()
                                ->live()
                                ->helperText('Compaiono gli sponsor con almeno una grafica: si caricano nella scheda dello sponsor.'),
                            CheckboxList::make('preroll')
                                ->label('Spot prima della diretta')
                                ->options(fn (Get $get): array => self::graficheDelloSponsor((int) $get('sponsor_id'), UsoCreativita::Preroll, $get('preroll') ?? []))
                                ->live()
                                ->visible(fn (Get $get): bool => self::graficheDelloSponsor((int) $get('sponsor_id'), UsoCreativita::Preroll, $get('preroll') ?? []) !== []),
                            CheckboxList::make('cornici')
                                ->label('Cornici durante la gara')
                                ->options(fn (Get $get): array => self::graficheDelloSponsor((int) $get('sponsor_id'), UsoCreativita::Cornice, $get('cornici') ?? []))
                                ->live()
                                ->visible(fn (Get $get): bool => self::graficheDelloSponsor((int) $get('sponsor_id'), UsoCreativita::Cornice, $get('cornici') ?? []) !== []),
                            Placeholder::make('anteprima')
                                ->label('Anteprima')
                                ->content(fn (Get $get): HtmlString => self::anteprimaDelleGrafiche(
                                    [...self::ids($get('preroll') ?? []), ...self::ids($get('cornici') ?? [])],
                                    (int) ($get('../../preroll_salta_dopo_secondi') ?? 5),
                                ))
                                ->visible(fn (Get $get): bool => self::ids($get('preroll') ?? []) !== [] || self::ids($get('cornici') ?? []) !== []),
                        ])
                        ->itemLabel(fn (array $state): ?string => self::nomiDegliSponsor()[(int) ($state['sponsor_id'] ?? 0)] ?? null)
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Aggiungi uno sponsor'),
                    Toggle::make('preroll_attivo')
                        ->label('Spot prima della diretta')
                        ->live(),
                    TextInput::make('preroll_salta_dopo_secondi')
                        ->label('Secondi prima di «Salta»')
                        ->helperText('0 = non si può saltare.')
                        ->numeric()->integer()->minValue(0)->maxValue(Diretta::PRIMA_DI_SALTA_MASSIMO)->required()
                        ->visible(fn (Get $get): bool => (bool) $get('preroll_attivo')),
                ]),
            Section::make('Durante la gara')
                ->description('Ogni tanto il player si riduce e accanto compare la cornice dello sponsor, poi torna grande. Le stesse cornici ruotano negli spot mandati dalla regia.')
                ->schema([
                    Toggle::make('ciclo_attivo')
                        ->label('Ciclo automatico')
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('ciclo_ogni_minuti')
                        ->label('Ogni quanti minuti')
                        ->numeric()->integer()->minValue(Diretta::CICLO_OGNI_MINUTI_MINIMO)->maxValue(Diretta::CICLO_OGNI_MINUTI_MASSIMO)->required()
                        ->live(onBlur: true),
                    TextInput::make('ciclo_durata_secondi')
                        ->label('Per quanti secondi')
                        ->numeric()->integer()->minValue(Diretta::CICLO_DURATA_MINIMA)->maxValue(Diretta::CICLO_DURATA_MASSIMA)->required(),
                    TextInput::make('ciclo_ritardo_minuti')
                        ->label('Minuti dopo l\'inizio della gara')
                        ->numeric()->integer()->minValue(0)->maxValue(Diretta::RITARDO_MASSIMO_MINUTI)->required()
                        ->live(onBlur: true),
                    TextInput::make('pausa_durata_predefinita_secondi')
                        ->label('Durata degli spot della regia (secondi)')
                        ->numeric()->integer()->minValue(Diretta::PAUSA_DURATA_MINIMA)->maxValue(Diretta::PAUSA_DURATA_MASSIMA)->required(),
                    Placeholder::make('anteprima_del_ciclo')
                        ->label('Primi cicli')
                        ->content(fn (Get $get): string => self::anteprima($game, $get))
                        ->columnSpanFull(),
                ])->columns(2),
        ];
    }

    /**
     * L'anteprima delle grafiche spuntate, come le vede il tifoso.
     *
     * @param  list<int>  $ids
     */
    private static function anteprimaDelleGrafiche(array $ids, int $saltaDopo): HtmlString
    {
        $html = LiveCreative::query()
            ->with(['media', 'sponsor'])
            ->whereKey($ids)
            ->get()
            ->sortBy(fn (LiveCreative $c): int|false => array_search($c->id, $ids, true))
            ->map(fn (LiveCreative $c): string => '<p style="font-weight:600;margin:12px 0 6px">'.e($c->nome).'</p>'
                .view('filament.components.anteprima-della-diretta', ['grafica' => $c->perLAnteprima(), 'saltaDopo' => $saltaDopo])->render())
            ->implode('');

        return new HtmlString($html);
    }

    /**
     * I nomi degli sponsor per le etichette del Repeater: una query sola per
     * richiesta invece di una per voce a ogni aggiornamento del modulo.
     *
     * @return array<int, string>
     */
    private static function nomiDegliSponsor(): array
    {
        return once(fn (): array => Sponsor::query()->pluck('name', 'id')->all());
    }

    /**
     * Le grafiche attive dello sponsor per un uso, come opzioni da spuntare,
     * più quelle già scelte per la gara e poi spente: segnate, perché si
     * possano vedere e togliere. Senza, restavano agganciate di nascosto e
     * tornavano in onda appena riaccese.
     *
     * @param  array<int|string, mixed>  $scelte
     * @return array<int, string>
     */
    private static function graficheDelloSponsor(int $sponsor, UsoCreativita $uso, array $scelte = []): array
    {
        if ($sponsor === 0) {
            return [];
        }

        $scelte = self::ids($scelte);

        return LiveCreative::query()
            ->where('sponsor_id', $sponsor)
            ->where('uso', $uso)
            ->where(fn ($q) => $q->where('attiva', true)->orWhereKey($scelte))
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (LiveCreative $c): array => [$c->id => $c->nome.' ('.$c->tipo->getLabel().', '.$c->durata_secondi.' s)'.($c->attiva ? '' : ' — spenta, non va in onda')])
            ->all();
    }

    /**
     * I primi cicli come andranno davvero: con il ritardo invariato valgono
     * gli orari salvati, che tengono conto di «Riparti da adesso» e della
     * gara spostata.
     */
    private static function anteprima(Game $game, Get $get): string
    {
        if (! $get('ciclo_attivo')) {
            return 'Ciclo spento: la cornice compare solo quando la manda la regia.';
        }

        $ogni = (int) $get('ciclo_ogni_minuti');
        $sessione = LiveSession::perLaGaraSenzaCrearla($game);
        $orari = $sessione->exists && $sessione->ciclo_inizio !== null && $sessione->ciclo_fine !== null
            && (int) $get('ciclo_ritardo_minuti') === $sessione->ciclo_ritardo_minuti
                ? ['ciclo_inizio' => $sessione->ciclo_inizio, 'ciclo_fine' => $sessione->ciclo_fine]
                : LiveSession::orariDelCiclo($game, (int) $get('ciclo_ritardo_minuti'));
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
