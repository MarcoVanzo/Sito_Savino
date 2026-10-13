<?php

namespace App\Services\Diretta;

use App\Enums\EventoDellaDiretta as Evento;
use App\Models\Game;
use App\Models\LiveAdStat;
use App\Models\LiveCreative;
use App\Models\Sponsor;
use Illuminate\Support\Collection;

/**
 * Il report per lo sponsor: per gara e per creatività, quante volte è andata
 * in onda e quante è stata cliccata, con il tasso di completamento del
 * pre-roll (visti fino in fondo su avviati).
 *
 * Una grafica o uno sponsor cancellati restano nel report col nome copiato
 * sui contatori (`sponsor_nome`, `creativita_nome`).
 */
class ReportDellaDiretta
{
    /**
     * @return list<array{gara: string, data: string, sponsor: string, creativita: string, preroll_avviati: int, preroll_completati: int, preroll_saltati: int, completamento: ?float, cicli: int, regia: int, clic: int}>
     */
    public function righe(?int $gara = null, ?int $sponsor = null): array
    {
        $contatori = LiveAdStat::query()
            ->selectRaw('game_id, live_creative_id, sponsor_nome, creativita_nome, evento, SUM(conteggio) as totale')
            ->when($gara, fn ($q) => $q->where('game_id', $gara))
            ->when($sponsor, fn ($q) => $q->where(fn ($q) => $q
                ->whereIn('live_creative_id', LiveCreative::query()->where('sponsor_id', $sponsor)->select('id'))
                ->orWhere(fn ($q) => $q->whereNull('live_creative_id')->where('sponsor_nome', Sponsor::query()->whereKey($sponsor)->value('name')))))
            ->groupBy('game_id', 'live_creative_id', 'sponsor_nome', 'creativita_nome', 'evento')
            ->get();

        if ($contatori->isEmpty()) {
            return [];
        }

        $gare = Game::query()->with(['homeTeam', 'awayTeam'])->whereIn('id', $contatori->pluck('game_id')->unique())->get()->keyBy('id');
        $creativita = LiveCreative::query()->with('sponsor')->whereIn('id', $contatori->pluck('live_creative_id')->unique())->get()->keyBy('id');

        return $contatori
            ->groupBy(fn (LiveAdStat $riga): string => $riga->game_id.'-'.($riga->live_creative_id ?? 'x-'.$riga->sponsor_nome.'-'.$riga->creativita_nome))
            ->map(function (Collection $gruppo) use ($gare, $creativita): array {
                /** @var LiveAdStat $prima */
                $prima = $gruppo->first();
                $totale = fn (Evento $evento): int => (int) $gruppo->first(fn (LiveAdStat $r) => $r->evento === $evento)?->getAttribute('totale');
                $partita = $gare->get($prima->game_id);
                $spot = $creativita->get($prima->live_creative_id);
                $avviati = $totale(Evento::PrerollAvviato);
                $completati = $totale(Evento::PrerollCompletato);

                return [
                    'gara' => $partita !== null ? $partita->homeTeam?->nomePubblico().' – '.$partita->awayTeam?->nomePubblico() : '#'.$prima->game_id,
                    'data' => (string) $partita?->match_date->format('d/m/Y H:i'),
                    'quando' => (int) $partita?->match_date->getTimestamp(),
                    'sponsor' => (string) ($spot->sponsor->name ?? $prima->sponsor_nome),
                    'creativita' => (string) ($spot->nome ?? $prima->creativita_nome),
                    'preroll_avviati' => $avviati,
                    'preroll_completati' => $completati,
                    'preroll_saltati' => $totale(Evento::PrerollSaltato),
                    'completamento' => $avviati > 0 ? round($completati / $avviati * 100, 1) : null,
                    'cicli' => $totale(Evento::CicloMostrato),
                    'regia' => $totale(Evento::PausaMostrata),
                    'clic' => $totale(Evento::Clic),
                ];
            })
            // Sulla data vera: `d/m/Y` come testo metteva il 31/01 prima del 13/10.
            ->sortBy([['quando', 'desc'], ['sponsor', 'asc'], ['creativita', 'asc']])
            ->map(function (array $riga): array {
                unset($riga['quando']);

                return $riga;
            })
            ->values()
            ->all();
    }

    /**
     * Il report come CSV per lo sponsor (separatore `;` e BOM: si apre
     * giusto in Excel con le impostazioni italiane).
     *
     * @param  list<array<string, mixed>>  $righe
     */
    public function csv(array $righe): string
    {
        $intestazioni = ['Gara', 'Data', 'Sponsor', 'Creatività', 'Pre-roll avviati', 'Pre-roll visti fino in fondo', 'Pre-roll saltati', 'Completamento %', 'Cornici (ciclo)', 'Cornici (regia)', 'Clic'];
        $file = fopen('php://temp', 'r+');
        fwrite($file, "\u{FEFF}");
        fputcsv($file, $intestazioni, ';');

        foreach ($righe as $riga) {
            fputcsv($file, [
                self::testo($riga['gara']), $riga['data'], self::testo($riga['sponsor']), self::testo($riga['creativita']),
                $riga['preroll_avviati'], $riga['preroll_completati'], $riga['preroll_saltati'],
                $riga['completamento'] === null ? '' : number_format($riga['completamento'], 1, ',', ''),
                $riga['cicli'], $riga['regia'], $riga['clic'],
            ], ';');
        }

        rewind($file);
        $csv = (string) stream_get_contents($file);
        fclose($file);

        return $csv;
    }

    /**
     * Un testo che Excel non scambia per una formula: il file va a un
     * destinatario esterno, e un nome che comincia con `=`, `+`, `-` o `@`
     * verrebbe eseguito all'apertura (CSV injection).
     */
    private static function testo(string $valore): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valore) === 1 ? "'".$valore : $valore;
    }
}
