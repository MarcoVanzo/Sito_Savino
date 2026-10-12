<?php

namespace App\Services\Diretta;

use App\Enums\StatoDellaDiretta;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lo stato della diretta di una gara come file JSON statico, accanto alle
 * creatività (Spaces in produzione).
 *
 * Durante una gara possono esserci mille spettatori che chiedono lo stato
 * ogni dieci secondi: un file statico con `max-age=5` non tocca né PHP né il
 * database. Si riscrive solo quando qualcosa cambia (salvataggio della
 * diretta, azione della regia, scadenza di una pausa); il ciclo automatico
 * lo calcola il browser dai parametri di `ciclo`, così il file non cambia a
 * ogni ciclo.
 *
 * Mai un'eccezione verso chi chiama: se Spaces non risponde il pannello lo
 * dice, e il sito mostra la diretta senza sponsor.
 */
class StatoPubblicoDellaDiretta
{
    public const CARTELLA = 'diretta';

    public function percorso(int $gara): string
    {
        return self::CARTELLA.'/'.$gara.'.json';
    }

    public function url(int $gara): string
    {
        return Storage::disk($this->disco())->url($this->percorso($gara));
    }

    public function pubblica(LiveSession $sessione): bool
    {
        try {
            return Storage::disk($this->disco())->put(
                $this->percorso($sessione->game_id),
                (string) json_encode($this->contenuto($sessione), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                [
                    'visibility' => 'public',
                    'ContentType' => 'application/json',
                    'CacheControl' => 'public, max-age=5',
                ],
            );
        } catch (Throwable $e) {
            Log::error('Stato della diretta non pubblicato', ['gara' => $sessione->game_id, 'errore' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function contenuto(LiveSession $sessione): array
    {
        $sessione->loadMissing(['creativitaDellaGara.media', 'creativitaDellaGara.sponsor', 'creativitaInOnda.media', 'creativitaInOnda.sponsor']);
        $oggi = now()->toDateString();
        $inPausa = $sessione->stato === StatoDellaDiretta::Pausa;

        return [
            'versione' => $sessione->versione,
            'ora_server' => now()->toIso8601String(),
            'stato' => $sessione->stato->value,
            'dalle' => $inPausa ? $sessione->pausa_dalle?->toIso8601String() : null,
            'fino_alle' => $inPausa ? $sessione->pausa_fino_alle?->toIso8601String() : null,
            'ciclo' => [
                'attivo' => $sessione->ciclo_attivo && $sessione->stato !== StatoDellaDiretta::Inattiva,
                'inizio' => $sessione->ciclo_inizio?->toIso8601String(),
                'fine' => $sessione->ciclo_fine?->toIso8601String(),
                'ogni_minuti' => $sessione->ciclo_ogni_minuti,
                'durata_secondi' => $sessione->ciclo_durata_secondi,
            ],
            'contenuto' => $inPausa && $sessione->creativitaInOnda !== null
                ? [...$sessione->creativitaInOnda->perIlSito(), 'durata' => $this->secondiDellaPausa($sessione)]
                : null,
            'playlist' => $sessione->creativitaDellaGara
                ->filter(fn (LiveCreative $c): bool => $c->attiva
                    && ($c->valida_al === null || $c->valida_al->toDateString() >= $oggi)
                    && $c->getFirstMedia(LiveCreative::COLLEZIONE_CONTENUTO) !== null)
                ->map(fn (LiveCreative $c): array => $c->perIlSito())
                ->values()
                ->all(),
        ];
    }

    private function secondiDellaPausa(LiveSession $sessione): int
    {
        if ($sessione->pausa_dalle === null || $sessione->pausa_fino_alle === null) {
            return 0;
        }

        return (int) $sessione->pausa_dalle->diffInSeconds($sessione->pausa_fino_alle);
    }

    /** Lo stesso disco delle creatività: in produzione Spaces, in locale `public`. */
    private function disco(): string
    {
        return (string) config('media-library.disk_name');
    }
}
