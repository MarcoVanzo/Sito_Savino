<?php

namespace App\Console\Commands;

use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Services\GalleryLegacy\LettoreGalleryVecchioSito;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Porta nella gallery del sito nuovo gli album del vecchio.
 *
 * `gallery:from-posts` aveva costruito la gallery con le copertine dei
 * comunicati, raggruppate per mese: non sono le foto della Gallery, e la
 * redazione lo ha segnalato. Le vere sono negli album di
 * savinodelbenevolley.it/gallery/, una pagina per partita o per evento.
 *
 * È idempotente: l'album si riconosce dal suo indirizzo di origine
 * (`legacy_slug`) e la foto dall'impronta del suo indirizzo, quindi rilanciarlo
 * non duplica nulla e riprende da dove si era fermato.
 */
class ImportaLaGalleryDalVecchioSito extends Command
{
    protected $signature = 'gallery:importa-dal-vecchio-sito
        {--dal= : importa solo gli album dal giorno indicato (YYYY-MM-DD)}
        {--limite= : quanti album al massimo}
        {--prova : elenca soltanto, senza scrivere né scaricare}
        {--togli-quelli-dai-comunicati : cancella gli album creati da gallery:from-posts}';

    protected $description = 'Importa gli album fotografici dalla Gallery del vecchio sito';

    /**
     * Le parole che distinguono una partita da un evento: la gallery pubblica
     * filtra su questa etichetta.
     */
    private const PAROLE_DI_GARA = [
        'giornata', 'gara', 'finale', 'semifinale', 'quarti', 'ottavi',
        'coppa', 'champions', 'cev', 'mondiale', 'play off', 'playoff',
        'supercoppa', 'andata', 'ritorno',
    ];

    public function handle(LettoreGalleryVecchioSito $lettore): int
    {
        ini_set('memory_limit', '512M');

        if ($this->option('togli-quelli-dai-comunicati')) {
            $this->togliGliAlbumDaiComunicati();
        }

        $dal = $this->option('dal');
        $limite = $this->option('limite') !== null ? (int) $this->option('limite') : null;

        $this->info('Leggo l\'elenco degli album dal vecchio sito…');
        $slug = $lettore->elencoAlbum();
        $this->info(count($slug).' album trovati.');

        // Gli album già completi si saltano senza nemmeno aprirne la pagina:
        // è ciò che permette di importare l'archivio a scaglioni, un pezzo per
        // volta, senza ricominciare da capo ogni volta.
        $giaFatti = $this->albumGiaImportati();

        $importati = 0;
        $foto = 0;
        $saltati = 0;

        foreach ($slug as $uno) {
            if ($limite !== null && $importati >= $limite) {
                break;
            }

            if (in_array($uno, $giaFatti, true)) {
                continue;
            }

            $album = $lettore->album($uno);

            if ($album === null) {
                $this->warn("  ✗ {$uno}: nessuna foto, salto.");
                $saltati++;

                continue;
            }

            if ($dal !== null && ($album['data'] === null || $album['data'] < $dal)) {
                continue;
            }

            if ($this->option('prova')) {
                $this->line(sprintf('  %s — %s (%d foto)', $album['data'] ?? '????-??-??', $album['titolo'], count($album['foto'])));
                $importati++;

                continue;
            }

            $foto += $this->importaAlbum($album);
            $importati++;
        }

        $this->newLine();
        $this->info("Album considerati: {$importati}. Foto aggiunte: {$foto}. Album senza foto: {$saltati}.");

        return self::SUCCESS;
    }

    /**
     * @param  array{slug: string, titolo: string, data: ?string, foto: list<string>}  $album
     */
    private function importaAlbum(array $album): int
    {
        $evento = GalleryEvent::firstOrNew(['legacy_slug' => $album['slug']]);

        if (! $evento->exists) {
            $evento->fill([
                'title' => ['it' => $album['titolo'], 'en' => $album['titolo']],
                'event_date' => $album['data'] ?? now()->toDateString(),
                'category' => $this->categoria($album['titolo']),
                'description' => null,
                'is_active' => true,
            ])->save();
        }

        $aggiunte = 0;

        foreach ($album['foto'] as $posizione => $indirizzo) {
            if ($this->aggiungiLaFoto($evento, $indirizzo, $posizione)) {
                $aggiunte++;
            }
        }

        $this->line(sprintf('  ✓ %s — %s (%d nuove su %d)', $evento->event_date->format('Y-m-d'), $album['titolo'], $aggiunte, count($album['foto'])));

        return $aggiunte;
    }

    private function aggiungiLaFoto(GalleryEvent $evento, string $indirizzo, int $posizione): bool
    {
        // Impronta dell'indirizzo di origine: si calcola senza scaricare il
        // file, quindi una seconda esecuzione non ripassa dalla rete.
        // Non è un uso crittografico: serve solo a riconoscere il duplicato.
        $impronta = md5($indirizzo);

        // Il confronto è dentro l'album, non su tutto l'archivio: la stessa
        // foto può comparire in due album (una gara e la festa di fine
        // stagione), e in quel caso ci deve stare in tutti e due. Confrontando
        // su tutto, il secondo album restava vuoto e, non essendo mai
        // completo, veniva riscaricato a ogni esecuzione.
        if (GalleryImage::where('gallery_event_id', $evento->id)->where('file_hash', $impronta)->exists()) {
            return false;
        }

        $risposta = Http::timeout(60)->retry(2, 1000, throw: false)->get($indirizzo);

        if (! $risposta->successful()) {
            $this->warn('    ⚠ non scaricata: '.basename($indirizzo));

            return false;
        }

        $nome = basename(parse_url($indirizzo, PHP_URL_PATH) ?: 'foto.jpg');
        $temporaneo = sys_get_temp_dir().'/gallery_'.$impronta.'_'.$nome;
        file_put_contents($temporaneo, $risposta->body());

        try {
            $immagine = GalleryImage::create([
                'gallery_event_id' => $evento->id,
                'title' => mb_substr($evento->getTranslation('title', 'it', false) ?: $nome, 0, 255),
                'category' => $evento->category,
                'sort_order' => $posizione,
                'file_hash' => $impronta,
                'is_active' => true,
                'needs_review' => true,
            ]);

            $immagine->addMedia($temporaneo)->usingFileName($nome)->toMediaCollection('gallery');
        } catch (\Throwable $errore) {
            $this->warn('    ⚠ '.basename($indirizzo).': '.$errore->getMessage());

            return false;
        } finally {
            if (is_file($temporaneo)) {
                @unlink($temporaneo);
            }
        }

        return true;
    }

    /**
     * Gli album già portati a termine (l'evento c'è e ha delle foto).
     *
     * @return list<string>
     */
    private function albumGiaImportati(): array
    {
        return GalleryEvent::query()
            ->whereNotNull('legacy_slug')
            ->has('galleryImages')
            ->pluck('legacy_slug')
            ->all();
    }

    private function categoria(string $titolo): string
    {
        $minuscolo = mb_strtolower($titolo);

        foreach (self::PAROLE_DI_GARA as $parola) {
            if (str_contains($minuscolo, $parola)) {
                return 'Partite';
            }
        }

        return 'Eventi';
    }

    /**
     * Toglie solo gli album costruiti dalle copertine dei comunicati: si
     * riconoscono dalla descrizione che quel comando scriveva. Tutto il resto
     * — compreso quello che la redazione ha creato a mano — resta.
     */
    private function togliGliAlbumDaiComunicati(): void
    {
        $daTogliere = GalleryEvent::query()
            ->whereNull('legacy_slug')
            ->where('description', 'like', 'Foto dalle news di %')
            ->pluck('id');

        if ($daTogliere->isEmpty()) {
            $this->info('Nessun album dai comunicati da togliere.');

            return;
        }

        if ($this->option('prova')) {
            $this->warn("Da togliere: {$daTogliere->count()} album creati dai comunicati.");

            return;
        }

        // Niente transazione: cancellare una riga porta con sé il file su
        // Spaces, e novecento chiamate di rete dentro una transazione
        // terrebbero i lock del database aperti per minuti. Qui interrompersi a
        // metà non fa danni: quello che resta si toglie alla passata dopo.
        GalleryImage::whereIn('gallery_event_id', $daTogliere)
            ->chunkById(50, function ($immagini) {
                foreach ($immagini as $immagine) {
                    $immagine->delete();
                }
            });

        GalleryEvent::whereIn('id', $daTogliere)->delete();

        $this->info("Tolti {$daTogliere->count()} album creati dai comunicati.");
    }
}
