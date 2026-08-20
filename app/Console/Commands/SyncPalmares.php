<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Services\Wikipedia\PalmaresImporter;
use App\Services\Wikipedia\PalmaresParser;
use App\Services\Wikipedia\WikipediaClient;
use App\Services\Wikipedia\WikipediaPageResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importa il palmarès delle atlete da Wikipedia.
 *
 * Serve per il primo caricamento della rosa e per i ripassi periodici; il
 * lavoro quotidiano si fa dal pannello, atleta per atleta.
 *
 * Non è schedulato in automatico: le voci cambiano poche volte l'anno e ogni
 * atleta costa da una a cinque richieste. Se un giorno lo si vorrà schedulato,
 * `--only-changed` è la modalità giusta — una sola richiesta per atleta per
 * confrontare la revisione, e il resto solo per chi è cambiata.
 */
class SyncPalmares extends Command
{
    protected $signature = 'palmares:sync
                            {player? : ID dell\'atleta. Se omesso si prende la rosa della stagione corrente.}
                            {--all : Tutte le atlete in archivio, non solo la rosa corrente.}
                            {--only-changed : Salta le atlete la cui voce non è cambiata dall\'ultima importazione.}
                            {--dry-run : Mostra cosa verrebbe importato senza scrivere.}';

    protected $description = 'Importa il palmarès delle atlete dalle voci di Wikipedia';

    public function handle(
        WikipediaClient $client,
        WikipediaPageResolver $resolver,
        PalmaresImporter $importer,
    ): int {
        $players = $this->players();

        if ($players->isEmpty()) {
            $this->warn('Nessuna atleta da elaborare.');

            return self::SUCCESS;
        }

        $rows = [];
        $failures = 0;

        foreach ($players as $player) {
            try {
                $rows[] = $this->syncPlayer($player, $client, $resolver, $importer);
            } catch (Throwable $e) {
                $failures++;
                Log::warning("Palmarès non importato per {$player->full_name}: ".$e->getMessage(), ['exception' => $e]);
                $rows[] = [$player->full_name, '—', 'errore', $e->getMessage()];
            }
        }

        $this->table(['Atleta', 'Voce Wikipedia', 'Esito', 'Dettaglio'], $rows);

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function syncPlayer(
        Player $player,
        WikipediaClient $client,
        WikipediaPageResolver $resolver,
        PalmaresImporter $importer,
    ): array {
        if ($this->option('only-changed') && $player->wikipedia_title !== null && $player->wikipedia_revid !== null) {
            $current = $client->revisionId($player->wikipedia_title);

            if ($current !== null && $current === $player->wikipedia_revid) {
                return [$player->full_name, $player->wikipedia_title, 'invariata', 'revisione '.$current];
            }
        }

        $page = $resolver->resolve($player);

        if ($page === null) {
            return [$player->full_name, '—', 'non trovata', 'nessuna voce attendibile'];
        }

        if ($this->option('dry-run')) {
            $parsed = app(PalmaresParser::class)->parse($page['wikitext']);

            return [$player->full_name, $page['title'], 'anteprima', count($parsed).' righe ('.$page['confidence'].')'];
        }

        $stats = $importer->import($player, $page['wikitext'], $page['title'], $page['revid'], $client->lang());

        return [
            $player->full_name,
            $page['title'],
            'importato',
            "{$stats['imported']} righe, {$stats['kept']} manuali conservate, {$stats['skipped']} scartate ({$page['confidence']})",
        ];
    }

    /**
     * @return Collection<int, Player>
     */
    private function players()
    {
        $id = $this->argument('player');

        if ($id !== null) {
            return Player::where('id', $id)->get();
        }

        if ($this->option('all')) {
            return Player::orderBy('last_name')->get();
        }

        $season = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

        if ($season === null) {
            return Player::orderBy('last_name')->get();
        }

        $playerIds = Roster::where('season_id', $season->id)->pluck('player_id')->unique();

        return Player::whereIn('id', $playerIds)->orderBy('last_name')->get();
    }
}
