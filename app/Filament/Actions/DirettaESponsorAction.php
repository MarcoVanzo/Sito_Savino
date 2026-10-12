<?php

namespace App\Filament\Actions;

use App\Enums\UsoCreativita;
use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Observers\CacheInvalidationObserver;
use App\Support\Diretta\CicloDellaCornice;
use App\Support\Diretta\DirettaPerIlSito;
use App\Support\Diretta\ImpostazioniDellaDiretta as Diretta;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * «Diretta e sponsor» sulla scheda della partita: pre-roll, creatività durante
 * la gara e ciclo automatico della cornice, gara per gara.
 *
 * È un'azione e non una sezione del modulo della gara perché la gara la
 * modifica l'area sportiva, mentre gli sponsor della diretta li cura la
 * comunicazione, che della gara ha solo la vista (GamePolicy). Compare solo
 * con l'interruttore generale acceso.
 */
class DirettaESponsorAction
{
    public static function make(string $name = 'direttaESponsor'): Action
    {
        return Action::make($name)
            ->label('Diretta e sponsor')
            ->icon('heroicon-o-play-circle')
            ->color('gray')
            ->modalHeading('Diretta e sponsor')
            ->modalDescription('Cosa vede chi guarda la diretta di questa gara dal sito. La diretta resta sempre visibile e comandabile: gli sponsor compaiono prima o accanto, mai sopra.')
            ->modalSubmitActionLabel('Salva')
            ->visible(fn (): bool => Diretta::abilitata() && (bool) auth()->user()?->can('update', LiveSession::class))
            ->fillForm(fn (Game $record): array => self::valori(LiveSession::perLaGaraSenzaCrearla($record)))
            ->form(fn (Game $record): array => self::campi($record))
            ->action(function (Game $record, array $data): void {
                self::salva($record, $data);

                Notification::make()->title('Diretta salvata')->success()->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function salva(Game $game, array $data): LiveSession
    {
        return DB::transaction(function () use ($game, $data): LiveSession {
            $sessione = LiveSession::perLaGara($game);

            $sessione->fill([
                'preroll_attivo' => (bool) ($data['preroll_attivo'] ?? false),
                'preroll_salta_dopo_secondi' => (int) $data['preroll_salta_dopo_secondi'],
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

            $sessione->ordina('creativitaDelPreroll', self::ids($data['preroll'] ?? []));
            $sessione->ordina('creativitaDellaGara', self::ids($data['gara'] ?? []));

            // Il pre-roll viaggia nelle pagine in cache di home e calendario.
            DB::afterCommit(fn () => app(CacheInvalidationObserver::class)->svuotaLeChiavi(DirettaPerIlSito::CHIAVI_DI_CACHE));

            return $sessione;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function valori(LiveSession $sessione): array
    {
        $esiste = $sessione->exists;

        return [
            'preroll_attivo' => $sessione->preroll_attivo,
            'preroll' => $esiste ? $sessione->creativitaDelPreroll()->pluck('live_creatives.id')->all() : [],
            'preroll_salta_dopo_secondi' => $sessione->preroll_salta_dopo_secondi,
            'gara' => $esiste ? $sessione->creativitaDellaGara()->pluck('live_creatives.id')->all() : [],
            'ciclo_attivo' => $sessione->ciclo_attivo,
            'ciclo_ogni_minuti' => $sessione->ciclo_ogni_minuti,
            'ciclo_durata_secondi' => $sessione->ciclo_durata_secondi,
            'ciclo_ritardo_minuti' => $sessione->ciclo_ritardo_minuti,
            'pausa_durata_predefinita_secondi' => $sessione->pausa_durata_predefinita_secondi,
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private static function campi(Game $game): array
    {
        return [
            Forms\Components\Section::make('Prima della diretta')
                ->schema([
                    Forms\Components\Toggle::make('preroll_attivo')
                        ->label('Spot prima della diretta')
                        ->live(),
                    Forms\Components\Repeater::make('preroll')
                        ->label('Spot (uno per visione, a rotazione)')
                        ->simple(self::sceltaCreativita(UsoCreativita::Preroll))
                        ->reorderable()
                        ->defaultItems(0)
                        ->addActionLabel('Aggiungi uno spot')
                        ->visible(fn (Get $get): bool => (bool) $get('preroll_attivo')),
                    Forms\Components\TextInput::make('preroll_salta_dopo_secondi')
                        ->label('Secondi prima di «Salta»')
                        ->helperText('0 = non si può saltare.')
                        ->numeric()->integer()->minValue(0)->maxValue(Diretta::PRIMA_DI_SALTA_MASSIMO)->required()
                        ->visible(fn (Get $get): bool => (bool) $get('preroll_attivo')),
                ]),
            Forms\Components\Section::make('Durante la gara')
                ->description('Ogni tanto il player si riduce e accanto compare la cornice dello sponsor, poi torna grande. Le stesse creatività ruotano negli spot mandati dalla regia.')
                ->schema([
                    Forms\Components\Repeater::make('gara')
                        ->label('Cornici in rotazione')
                        ->simple(self::sceltaCreativita(UsoCreativita::Cornice))
                        ->reorderable()
                        ->defaultItems(0)
                        ->addActionLabel('Aggiungi una cornice')
                        ->columnSpanFull(),
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

    private static function sceltaCreativita(UsoCreativita $uso): Forms\Components\Select
    {
        return Forms\Components\Select::make('id')
            ->label('Creatività')
            ->options(fn (): array => LiveCreative::query()
                ->with('sponsor')
                ->where('uso', $uso)
                ->where('attiva', true)
                ->orderBy('nome')
                ->get()
                ->mapWithKeys(fn (LiveCreative $c): array => [$c->id => $c->nome.' — '.$c->sponsor?->name])
                ->all())
            ->searchable()
            ->distinct()
            ->required();
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
