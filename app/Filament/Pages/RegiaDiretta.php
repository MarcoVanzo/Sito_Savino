<?php

namespace App\Filament\Pages;

use App\Enums\StatoDellaDiretta;
use App\Filament\Resources\GameResource;
use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Services\Diretta\RegiaDellaDiretta;
use App\Support\Diretta\CicloDellaCornice;
use App\Support\Diretta\ImpostazioniDellaDiretta as Diretta;
use App\Support\MatchDay;
use Closure;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * La regia della diretta, dal telefono a bordo campo. Facoltativa: senza
 * nessuno qui il ciclo automatico va da solo.
 *
 * Pulsanti grandi e senza conferma per gli spot (servono rapidi fra un set e
 * l'altro), conferma solo su «Spegni tutto». Il pubblico vede il cambio entro
 * una decina di secondi (il sito rilegge lo stato ogni dieci).
 */
class RegiaDiretta extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Sponsor';

    protected static ?string $navigationLabel = 'Regia diretta';

    protected static ?string $title = 'Regia della diretta';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'regia-diretta';

    protected static string $view = 'filament.pages.regia-diretta';

    /** Giorni attorno a oggi fra cui scegliere la gara. */
    private const GIORNI_PRIMA = 1;

    private const GIORNI_DOPO = 7;

    public ?int $gara = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('update', LiveSession::class);
    }

    /** Spento l'interruttore la regia non serve: sparisce dal menu, la pagina lo dice. */
    public static function shouldRegisterNavigation(): bool
    {
        return Diretta::abilitata() && static::canAccess();
    }

    public function abilitata(): bool
    {
        return Diretta::abilitata();
    }

    public function mount(): void
    {
        $this->gara = MatchDay::garaDiOggi()->id ?? $this->gare()->first()?->id;
    }

    /** @return Collection<int, Game> */
    public function gare(): Collection
    {
        return Game::query()
            ->with(['homeTeam', 'awayTeam'])
            ->whereBetween('match_date', [now()->subDays(self::GIORNI_PRIMA)->startOfDay(), now()->addDays(self::GIORNI_DOPO)->endOfDay()])
            ->where(fn (Builder $q) => $q
                ->whereHas('homeTeam', fn (Builder $t) => $t->where('is_internal', true))
                ->orWhereHas('awayTeam', fn (Builder $t) => $t->where('is_internal', true)))
            ->orderBy('match_date')
            ->get();
    }

    public function sessione(): ?LiveSession
    {
        return $this->gara === null
            ? null
            : LiveSession::query()->with(['creativitaInOnda', 'creativitaDellaGara'])->where('game_id', $this->gara)->first();
    }

    /**
     * Quello che la pagina mostra della diretta scelta.
     *
     * @return array{stato: StatoDellaDiretta, in_onda: ?string, fino_alle: ?string, ciclo_attivo: bool, prossimo_ciclo: ?string, cornici: int}|null
     */
    public function riepilogo(): ?array
    {
        $sessione = $this->sessione();

        if ($sessione === null) {
            return null;
        }

        $prossimo = $sessione->ciclo_attivo && $sessione->ciclo_inizio && $sessione->ciclo_fine
            ? CicloDellaCornice::prossimo($sessione->ciclo_inizio, $sessione->ciclo_fine, $sessione->ciclo_ogni_minuti, $sessione->ciclo_durata_secondi, now())
            : null;

        return [
            'stato' => $sessione->stato,
            'in_onda' => $sessione->stato === StatoDellaDiretta::Pausa
                ? ($sessione->creativitaInOnda->nome ?? 'Cornici a rotazione')
                : null,
            'fino_alle' => $sessione->stato === StatoDellaDiretta::Pausa ? $sessione->pausa_fino_alle?->toIso8601String() : null,
            'ciclo_attivo' => $sessione->ciclo_attivo,
            'prossimo_ciclo' => $prossimo !== null ? $prossimo['inizio']->format('H:i:s') : null,
            'cornici' => $sessione->creativitaDellaGara->count(),
        ];
    }

    public function urlDellaGara(): ?string
    {
        return $this->gara !== null ? GameResource::getUrl('view', ['record' => $this->gara]) : null;
    }

    public function pausaTraISetAction(): Action
    {
        return $this->azione('pausaTraISet', 'Pausa tra i set', 'heroicon-o-pause-circle', 'warning',
            fn (RegiaDellaDiretta $regia, LiveSession $s) => $regia->pausaTraISet($s))
            ->disabled(fn (): bool => ($this->riepilogo()['cornici'] ?? 0) === 0);
    }

    public function mostraAction(): Action
    {
        return $this->azione('mostra', 'Mostra…', 'heroicon-o-megaphone', 'primary',
            function (RegiaDellaDiretta $regia, LiveSession $s, array $data): bool {
                $creativita = $s->creativitaDellaGara()->findOrFail($data['creativita']);

                return $regia->mostra($s, $creativita, (int) $data['secondi']);
            })
            ->disabled(fn (): bool => ($this->riepilogo()['cornici'] ?? 0) === 0)
            ->modalHeading('Mostra una cornice')
            ->modalSubmitActionLabel('Manda in onda')
            ->form(fn (): array => [
                Forms\Components\Select::make('creativita')
                    ->label('Cornice')
                    // Solo le cornici di questa gara: sono quelle che il sito
                    // conosce e che il report accetta.
                    ->options(fn (): array => $this->corniciDaMostrare())
                    ->in(fn (): array => array_keys($this->corniciDaMostrare()))
                    ->required(),
                Forms\Components\TextInput::make('secondi')
                    ->label('Per quanti secondi')
                    ->numeric()->integer()
                    ->minValue(Diretta::PAUSA_DURATA_MINIMA)->maxValue(Diretta::PAUSA_DURATA_MASSIMA)
                    ->default(fn (): int => $this->sessione()->pausa_durata_predefinita_secondi ?? 60)
                    ->required(),
            ]);
    }

    /**
     * Le cornici valide di questa gara, per «Mostra…».
     *
     * @return array<int, string>
     */
    private function corniciDaMostrare(): array
    {
        return ($this->sessione()?->creativitaDellaGara()->with(['sponsor', 'media'])->get() ?? collect())
            ->filter(fn (LiveCreative $c): bool => $c->valida())
            ->mapWithKeys(fn (LiveCreative $c): array => [$c->id => $c->nome.' — '.$c->sponsor?->name])
            ->all();
    }

    public function finePausaAction(): Action
    {
        return $this->azione('finePausa', 'Fine pausa', 'heroicon-o-play-circle', 'success',
            fn (RegiaDellaDiretta $regia, LiveSession $s) => $regia->finePausa($s))
            ->visible(fn (): bool => $this->sessione()?->stato === StatoDellaDiretta::Pausa);
    }

    public function cicloAction(): Action
    {
        return $this->azione('ciclo', fn (): string => $this->sessione()?->ciclo_attivo ? 'Sospendi ciclo' : 'Riprendi ciclo', 'heroicon-o-arrow-path', 'gray',
            fn (RegiaDellaDiretta $regia, LiveSession $s) => $s->ciclo_attivo ? $regia->sospendiCiclo($s) : $regia->riprendiCiclo($s))
            ->visible(fn (): bool => $this->sessione()?->stato !== StatoDellaDiretta::Inattiva);
    }

    public function ripartiDaAdessoAction(): Action
    {
        return $this->azione('ripartiDaAdesso', 'Riparti da adesso', 'heroicon-o-clock', 'gray',
            fn (RegiaDellaDiretta $regia, LiveSession $s) => $regia->ripartiDaAdesso($s))
            ->visible(fn (): bool => $this->sessione()?->stato !== StatoDellaDiretta::Inattiva);
    }

    public function spegniTuttoAction(): Action
    {
        return $this->azione('spegniTutto', 'Spegni tutto', 'heroicon-o-no-symbol', 'danger',
            fn (RegiaDellaDiretta $regia, LiveSession $s) => $regia->spegniTutto($s))
            ->requiresConfirmation()
            ->modalDescription('Nessuno sponsor durante questa gara finché non riaccendi. Il pre-roll resta.')
            ->visible(fn (): bool => $this->sessione()?->stato !== StatoDellaDiretta::Inattiva);
    }

    public function riaccendiAction(): Action
    {
        return $this->azione('riaccendi', 'Riaccendi', 'heroicon-o-bolt', 'success',
            fn (RegiaDellaDiretta $regia, LiveSession $s) => $regia->riaccendi($s))
            ->visible(fn (): bool => $this->sessione()?->stato === StatoDellaDiretta::Inattiva);
    }

    /**
     * @param  string|Closure(): string  $etichetta
     * @param  Closure(RegiaDellaDiretta, LiveSession, array<string, mixed>): bool  $esegui
     */
    private function azione(string $nome, string|Closure $etichetta, string $icona, string $colore, Closure $esegui): Action
    {
        return Action::make($nome)
            ->label($etichetta)
            ->icon($icona)
            ->color($colore)
            ->size('xl')
            ->extraAttributes(['class' => 'w-full justify-center py-4'])
            ->authorize(fn (): bool => static::canAccess() && Diretta::abilitata())
            ->action(function (array $data) use ($esegui): void {
                $sessione = $this->sessione();

                if ($sessione === null) {
                    return;
                }

                $pubblicato = $esegui(app(RegiaDellaDiretta::class), $sessione, $data);

                $pubblicato
                    ? Notification::make()->title('Fatto: il sito lo mostra entro una decina di secondi')->success()->send()
                    : Notification::make()->title('Salvato, ma il sito non l\'ha ricevuto')->body('Lo stato non si è potuto scrivere sull\'archivio dei file: riprova.')->warning()->send();
            });
    }
}
