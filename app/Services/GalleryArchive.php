<?php

namespace App\Services;

use App\Models\GalleryImage;
use App\Models\Player;
use Illuminate\Support\Facades\Cache;

/**
 * L'archivio fotografico nella forma che il front-end si aspetta, e la sua
 * cache.
 *
 * Sono oltre dodicimila foto: costruire l'elenco — una query con media,
 * atlete ed eventi, e due indirizzi su Spaces per foto — costa una decina di
 * secondi. Con una cache di trenta minuti che si svuotava a ogni foto
 * salvata (anche dal job di riconoscimento dei volti), quei secondi li
 * aspettava il primo visitatore dopo ogni modifica: la gallery "si apriva
 * lenta" senza che nessuno capisse quando.
 *
 * Adesso l'archivio completo resta in cache un giorno e si rigenera in coda
 * (RicostruisciLaCacheDellaGallery) quando cambia qualcosa e comunque ogni
 * ora: il visitatore trova sempre una copia pronta, al massimo vecchia di
 * qualche minuto. Le varianti per atleta sono piccole e si buttano via come
 * prima.
 */
class GalleryArchive
{
    public const CHIAVE = 'public:gallery_images';

    /** Foto lette per volta: dodicimila modelli con le relazioni non stanno in memoria tutti insieme. */
    private const BLOCCO = 500;

    /**
     * @return list<array<string, mixed>>
     */
    public function media(?Player $filtro, string $locale): array
    {
        return Cache::remember($this->chiave($filtro, $locale), $this->durata(), fn (): array => $this->costruisci($filtro, $locale));
    }

    /**
     * Rigenera la copia in cache dell'archivio completo, per ogni lingua.
     */
    public function riscalda(): void
    {
        foreach (config('app.supported_locales', ['it']) as $locale) {
            Cache::put($this->chiave(null, $locale), $this->costruisci(null, $locale), $this->durata());
        }
    }

    public function chiave(?Player $filtro, string $locale): string
    {
        return $filtro
            ? self::CHIAVE.':player_'.$filtro->id.':'.$locale
            : self::CHIAVE.':'.$locale;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function costruisci(?Player $filtro, string $locale): array
    {
        $query = GalleryImage::active()->ordered()
            ->with(['media', 'players:id,first_name,last_name', 'galleryEvent:id,title,event_date']);

        if ($filtro) {
            $query->whereHas('players', fn ($q) => $q->where('players.id', $filtro->id));
        }

        $foto = [];

        foreach ($query->lazy(self::BLOCCO) as $img) {
            $foto[] = $this->presentaLaFoto($img, $locale);
        }

        return $foto;
    }

    private function durata(): \DateTimeInterface
    {
        return now()->addDay();
    }

    /**
     * Una foto dell'archivio, nella forma che il front-end si aspetta.
     *
     * @return array<string, mixed>
     */
    private function presentaLaFoto(GalleryImage $img, string $locale): array
    {
        return [
            'id' => $img->id,
            'url' => $img->getFirstMediaUrl('gallery', 'lightbox') ?: $img->getFirstMediaUrl('gallery'),
            'thumb' => $img->getFirstMediaUrl('gallery', 'thumb') ?: $img->getFirstMediaUrl('gallery'),
            'alt' => mb_substr($this->testoTradotto($img->title ?? __('Immagine Galleria'), $locale), 0, 255),
            'category' => $img->category ?? 'Partite',
            'tags' => $img->players->map(fn ($p) => $p->full_name)->values()->toArray(),
            'event_name' => $this->testoTradotto($img->galleryEvent?->title, $locale),
            // La pagina raggruppa le foto per album: senza l'identificativo
            // dovrebbe fidarsi del titolo, e due eventi omonimi in stagioni
            // diverse finirebbero nella stessa cartella.
            'event_id' => $img->gallery_event_id,
            'event_date' => $img->galleryEvent?->event_date?->toDateString(),
        ];
    }

    /**
     * Testo nella lingua richiesta, anche quando in colonna c'e' il JSON per
     * lingua invece della sola stringa.
     *
     * Alcuni titoli storici sono stati troncati in scrittura e il JSON e'
     * rimasto aperto: si tenta di richiuderlo nei due modi possibili prima di
     * arrendersi e restituire il testo grezzo.
     */
    private function testoTradotto(mixed $text, string $locale): string
    {
        if (! is_string($text) || ! str_starts_with($text, '{"it":')) {
            return is_string($text) ? $text : '';
        }

        foreach ([$text, $text.'"}', $text.'}'] as $tentativo) {
            $decoded = json_decode($tentativo, true);

            if (is_array($decoded) && (isset($decoded[$locale]) || isset($decoded['it']))) {
                return (string) ($decoded[$locale] ?? $decoded['it']);
            }
        }

        return $text;
    }
}
