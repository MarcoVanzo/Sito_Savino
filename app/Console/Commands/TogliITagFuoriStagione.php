<?php

namespace App\Console\Commands;

use App\Models\GalleryImage;
use App\Models\Player;
use App\Observers\CacheInvalidationObserver;
use App\Services\RevocaDelRiconoscimentoDeiVolti;
use App\Support\StagioniDelleAtlete;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Toglie i tag automatici delle atlete su foto di stagioni in cui non erano in
 * squadra (StagioniDelleAtlete): le nuove arrivate finivano riconosciute su
 * foto di anni prima, con somiglianze al 98-99%.
 *
 * Tocca solo i tag della macchina (`confidence_score` non nullo): quelli
 * messi dalla redazione restano anche fuori stagione. Le foto da cui toglie
 * un nome hanno i testi riscritti come alla revoca del riconoscimento. Il job
 * di analisi applica la stessa regola, quindi un nuovo giro non li rimette.
 */
class TogliITagFuoriStagione extends Command
{
    protected $signature = 'volti:togli-fuori-stagione {--dry-run : elenca senza modificare}';

    protected $description = 'Toglie i tag automatici delle atlete su foto di stagioni in cui non erano in squadra';

    public function handle(RevocaDelRiconoscimentoDeiVolti $revoca): int
    {
        $prova = (bool) $this->option('dry-run');

        $righe = DB::table('gallery_image_person as tag')
            ->join('gallery_images as foto', 'foto.id', '=', 'tag.gallery_image_id')
            ->join('gallery_events as album', 'album.id', '=', 'foto.gallery_event_id')
            ->where('tag.person_type', Player::class)
            ->whereNotNull('tag.confidence_score')
            ->whereNotNull('album.event_date')
            ->get(['tag.id', 'tag.gallery_image_id', 'tag.person_id', 'album.event_date']);

        $atlete = Player::withTrashed()->whereIn('id', $righe->pluck('person_id')->unique())->get()->keyBy('id');

        $daTogliere = $righe->filter(function ($riga) use ($atlete): bool {
            $atleta = $atlete->get($riga->person_id);

            return $atleta !== null
                && StagioniDelleAtlete::eraInSquadra($atleta, Carbon::parse($riga->event_date)) === false;
        });

        $perAtleta = $daTogliere->groupBy('person_id')
            ->map(fn ($tag, $id) => [$atlete[$id]->full_name, $tag->count()])
            ->sortByDesc(fn ($r) => $r[1])->values()->all();
        $senzaStagioni = $atlete->filter(fn (Player $a) => StagioniDelleAtlete::stagioniDi($a) === null)
            ->map->full_name->sort()->values()->all();

        $this->table(['Atleta', 'Tag fuori stagione'], $perAtleta);

        if ($senzaStagioni !== []) {
            $this->warn('Senza stagioni nel file, non giudicate: '.implode(', ', $senzaStagioni));
        }

        if ($prova) {
            $this->info("Prova: {$daTogliere->count()} tag da togliere su {$daTogliere->pluck('gallery_image_id')->unique()->count()} foto. Nessuna modifica.");

            return self::SUCCESS;
        }

        DB::table('gallery_image_person')->whereIn('id', $daTogliere->pluck('id'))->delete();

        $titoliDaRivedere = [];
        $ultimaFoto = null;

        foreach ($daTogliere->groupBy('gallery_image_id') as $idFoto => $tag) {
            $foto = GalleryImage::with('media')->find($idFoto);

            if ($foto === null) {
                continue;
            }

            foreach ($tag as $riga) {
                if (! $revoca->ripulisciITesti($foto, $atlete[$riga->person_id]->full_name)) {
                    $titoliDaRivedere[] = $foto->id;
                }
            }

            $ultimaFoto = $foto;
        }

        // Salvataggi silenziosi: la cache della gallery si rigenera una volta.
        if ($ultimaFoto !== null) {
            app(CacheInvalidationObserver::class)->saved($ultimaFoto);
        }

        $this->info("Tolti {$daTogliere->count()} tag su {$daTogliere->pluck('gallery_image_id')->unique()->count()} foto.");

        if ($titoliDaRivedere !== []) {
            $this->warn('Titoli scritti a mano che nominano ancora l\'atleta (foto da rivedere): '.implode(', ', array_unique($titoliDaRivedere)));
        }

        return self::SUCCESS;
    }
}
