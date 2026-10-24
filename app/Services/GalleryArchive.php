<?php

namespace App\Services;

use App\Models\GalleryImage;
use App\Models\Player;
use App\Support\GenerazioneDiCache;
use App\Support\TestiSeoDellaFoto;
use DateTimeInterface;
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

    /** Famiglia di `GenerazioneDiCache` delle varianti per atleta. */
    private const GENERAZIONE_VARIANTI = 'gallery_images:atlete';

    /** Foto lette per volta: dodicimila modelli con le relazioni non stanno in memoria tutti insieme. */
    private const BLOCCO = 500;

    /** Segna il valore compresso, per distinguerlo dalle copie scritte prima (array nudi). */
    private const COMPRESSO = 'gz:';

    /**
     * Archivi già letti in questa richiesta, per chiave. L'istanza è
     * `scoped` (AppServiceProvider): una pagina d'album lo chiedeva tre volte
     * (controller due, meta dell'anteprima una) e ognuna riscaricava la riga.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $letti = [];

    /**
     * @return list<array<string, mixed>>
     */
    public function media(?Player $filtro, string $locale): array
    {
        $chiave = $this->chiave($filtro, $locale);

        if (isset($this->letti[$chiave])) {
            return $this->letti[$chiave];
        }

        $foto = $this->decomprimi(Cache::get($chiave));

        if ($foto === null) {
            $foto = $this->costruisci($filtro, $locale);
            Cache::put($chiave, $this->comprimi($foto), $this->durata());
        }

        return $this->letti[$chiave] = $foto;
    }

    /**
     * Rigenera la copia in cache dell'archivio completo, per ogni lingua.
     */
    public function riscalda(): void
    {
        foreach (config('app.supported_locales', ['it']) as $locale) {
            $chiave = $this->chiave(null, $locale);
            $this->letti[$chiave] = $this->costruisci(null, $locale);
            Cache::put($chiave, $this->comprimi($this->letti[$chiave]), $this->durata());
        }
    }

    /**
     * L'archivio serializzato pesava 8 MB in una colonna `mediumtext` che ne
     * tiene 16: con la gallery che cresce la scrittura sarebbe fallita
     * (errore 1406) e, scaduta la copia, la pagina con lei. Scaricare quegli
     * 8 MB era anche quasi tutto il secondo di `/gallery/data`. Compresso
     * scende a una frazione; base64 perché la colonna è testo utf8mb4 e
     * rifiuta i byte non validi.
     *
     * @param  list<array<string, mixed>>  $foto
     */
    private function comprimi(array $foto): string
    {
        return self::COMPRESSO.base64_encode((string) gzcompress(serialize($foto)));
    }

    /**
     * Il valore in cache tornato array, o null se manca o non si legge
     * (allora si ricostruisce). Le copie scritte prima della compressione
     * sono array nudi e valgono finché il job orario non le sostituisce.
     *
     * @return list<array<string, mixed>>|null
     */
    private function decomprimi(mixed $valore): ?array
    {
        if (is_array($valore)) {
            return array_values($valore);
        }

        if (! is_string($valore) || ! str_starts_with($valore, self::COMPRESSO)) {
            return null;
        }

        $serializzato = @gzuncompress((string) base64_decode(substr($valore, strlen(self::COMPRESSO)), true));
        $foto = $serializzato === false ? false : @unserialize($serializzato, ['allowed_classes' => false]);

        return is_array($foto) ? array_values($foto) : null;
    }

    public function chiave(?Player $filtro, string $locale): string
    {
        return $filtro
            ? self::CHIAVE.':player_'.$filtro->id.':'.GenerazioneDiCache::attuale(self::GENERAZIONE_VARIANTI).':'.$locale
            : self::CHIAVE.':'.$locale;
    }

    /**
     * Butta le varianti per atleta con una sola scrittura, invece di una
     * query per atleta e per lingua.
     */
    public static function dimenticaLeVariantiPerAtleta(): void
    {
        GenerazioneDiCache::rinnova(self::GENERAZIONE_VARIANTI);
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

    private function durata(): DateTimeInterface
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
            // Il titolo (con i nomi delle persone taggate) resta la didascalia;
            // l'alt dice solo album e data, senza nomi: TestiSeoDellaFoto::altDellAlbum().
            'title' => mb_substr($this->testoTradotto($img->title ?? __('Immagine Galleria'), $locale), 0, 255),
            'alt' => mb_substr(TestiSeoDellaFoto::altDellAlbum(
                $this->testoTradotto($img->galleryEvent?->title, $locale),
                $img->galleryEvent?->event_date?->format('d/m/Y'),
            ), 0, 255),
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
