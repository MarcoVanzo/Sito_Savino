<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use XMLWriter;

/**
 * Feed RSS 2.0 delle notizie del sito.
 *
 * Serve alla Lega Pallavolo Serie A Femminile, che riprende i comunicati delle
 * società da un feed: l'indirizzo è pubblicato a terzi e non va cambiato senza
 * avvisarli (vedi CLAUDE.md §22).
 *
 * Gli indirizzi nascono da `route()` / `url()`, quindi seguono APP_URL
 * dell'ambiente che serve la richiesta, come la sitemap: un feed non si scrive
 * come file statico in `public/`, o il web server lo servirebbe prima che
 * Laravel veda la richiesta e conterrebbe il dominio della macchina che lo ha
 * generato.
 */
class NewsFeedBuilder
{
    /**
     * Quante notizie entrano nel feed. Trenta coprono abbondantemente il ritmo
     * di pubblicazione della redazione (qualche comunicato a settimana) anche
     * se un aggregatore lo rilegge una volta al mese.
     */
    public const NUMERO_DI_NOTIZIE = 30;

    /**
     * Mezz'ora: il feed non è una pagina che il tifoso ricarica, lo leggono i
     * lettori automatici. `CacheInvalidationObserver` lo butta comunque appena
     * la redazione salva una notizia, quindi la durata è solo il tetto.
     */
    private const CACHE_TTL = 30 * 60;

    public static function chiaveDiCache(string $locale): string
    {
        return 'public:news_feed:'.$locale;
    }

    /**
     * L'indirizzo del feed nella lingua chiesta. Lo usa anche il layout, per
     * il `<link rel="alternate">` con cui i lettori automatici lo trovano: la
     * regola del prefisso sta scritta una volta sola.
     */
    public static function indirizzo(string $locale): string
    {
        return route(self::prefissoDeiNomi($locale).'news.feed');
    }

    /**
     * XML del feed, ricalcolato al più ogni mezz'ora.
     */
    public function render(string $locale): string
    {
        return Cache::remember(
            self::chiaveDiCache($locale),
            self::CACHE_TTL,
            fn () => $this->build($locale)
        );
    }

    public function build(string $locale): string
    {
        $prefissoDeiNomi = self::prefissoDeiNomi($locale);
        $indirizzoDelleNews = route($prefissoDeiNomi.'news.index');

        $notizie = Post::published()
            ->with(['author', 'categories', 'media'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take(self::NUMERO_DI_NOTIZIE)
            ->get();

        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('    ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $xml->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $xml->writeAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');

        $xml->startElement('channel');

        $xml->writeElement('title', __('site.feed.title', [], $locale));
        $xml->writeElement('link', $indirizzoDelleNews);
        $xml->writeElement('description', __('site.feed.description', [], $locale));
        $xml->writeElement('language', $locale === 'en' ? 'en-US' : 'it-IT');
        $xml->writeElement('copyright', $this->copyright());
        $xml->writeElement('generator', 'Savino Del Bene Volley');
        // Minuti: è la stessa mezz'ora della cache e dell'header
        // `Cache-Control`, così l'aggregatore non torna prima che ci sia
        // qualcosa di nuovo da leggere.
        $xml->writeElement('ttl', (string) (self::CACHE_TTL / 60));

        // `lastBuildDate` è la data dell'ultima notizia, non `now()`: con l'ora
        // corrente ogni rilettura sembrerebbe portare novità.
        $piuRecente = $notizie->first();
        $ultimaModifica = $piuRecente === null
            ? null
            : ($piuRecente->published_at ?? $piuRecente->created_at);

        if ($ultimaModifica !== null) {
            $xml->writeElement('lastBuildDate', $ultimaModifica->toRfc2822String());
            $xml->writeElement('pubDate', $ultimaModifica->toRfc2822String());
        }

        // L'indirizzo canonico del feed, che gli aggregatori usano per
        // riconoscerlo anche se ci sono arrivati da un altro link.
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', route($prefissoDeiNomi.'news.feed'));
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        $xml->startElement('image');
        $xml->writeElement('url', url('/images/logo.png'));
        $xml->writeElement('title', __('site.feed.title', [], $locale));
        $xml->writeElement('link', $indirizzoDelleNews);
        $xml->endElement();

        foreach ($notizie as $notizia) {
            $this->scriviLaNotizia($xml, $notizia, $locale, $prefissoDeiNomi);
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function scriviLaNotizia(XMLWriter $xml, Post $notizia, string $locale, string $prefissoDeiNomi): void
    {
        $indirizzo = route($prefissoDeiNomi.'news.show', ['slug' => $notizia->slug]);
        $contenuto = $this->conIndirizziAssoluti($notizia->testoTradotto('content', $locale));

        $xml->startElement('item');

        $xml->writeElement('title', $this->ripulito($notizia->testoTradotto('title', $locale)));
        $xml->writeElement('link', $indirizzo);

        // L'identificativo con cui gli aggregatori riconoscono una notizia già
        // letta. Non è l'indirizzo: la redazione può correggere uno slug, e
        // con il link come guid quella notizia ricomparirebbe come nuova a
        // tutti gli abbonati. Per lo stesso motivo il feed di WordPress usa
        // `?p=<id>` con `isPermaLink="false"`, ed è la forma che la Lega
        // riceve oggi. Cambiare questo formato ripubblica l'intero archivio:
        // non si tocca.
        $xml->startElement('guid');
        $xml->writeAttribute('isPermaLink', 'false');
        $xml->text('urn:savinodelbenevolley:notizia:'.$notizia->getKey());
        $xml->endElement();

        $data = $notizia->published_at ?? $notizia->created_at;
        if ($data) {
            $xml->writeElement('pubDate', $data->toRfc2822String());
        }

        if ($notizia->author?->name) {
            $xml->writeElement('dc:creator', $notizia->author->name);
        }

        foreach ($notizia->categories as $categoria) {
            $xml->writeElement('category', $this->ripulito($categoria->testoTradotto('name', $locale)));
        }

        $xml->startElement('description');
        $xml->writeCdata($this->perIlCdata($this->sommario($notizia, $locale, $contenuto)));
        $xml->endElement();

        if ($contenuto !== '') {
            $xml->startElement('content:encoded');
            $xml->writeCdata($this->perIlCdata($this->ripulito($contenuto)));
            $xml->endElement();
        }

        $this->scriviLImmagine($xml, $notizia);

        $xml->endElement(); // item
    }

    /**
     * L'immagine di copertina, dichiarata nei due modi che gli aggregatori
     * leggono: `enclosure` (RSS 2.0) e `media:content` (Media RSS).
     */
    private function scriviLImmagine(XMLWriter $xml, Post $notizia): void
    {
        $immagine = $notizia->getFirstMedia('cover');

        if ($immagine === null) {
            return;
        }

        $indirizzo = $immagine->getUrl();

        if ($indirizzo === '') {
            return;
        }

        $xml->startElement('enclosure');
        $xml->writeAttribute('url', $indirizzo);
        $xml->writeAttribute('type', $this->tipoDellImmagine($indirizzo, $immagine->mime_type));
        $xml->writeAttribute('length', (string) ($immagine->size ?: 0));
        $xml->endElement();

        // `media:content` porta la versione da 1200 px che usa anche la pagina
        // della notizia: l'originale caricato dalla redazione pesa qualche
        // mega e l'aggregatore lo scaricherebbe per intero. Si controlla che
        // la conversione esista davvero, o si pubblicherebbe l'indirizzo di un
        // file mai generato (l'archivio importato non le ha tutte).
        $perIlWeb = $immagine->hasGeneratedConversion('detail')
            ? $immagine->getUrl('detail')
            : $indirizzo;

        $xml->startElement('media:content');
        $xml->writeAttribute('url', $perIlWeb);
        $xml->writeAttribute('medium', 'image');
        // Il tipo si legge dall'indirizzo servito, non dal media: le
        // conversioni di spatie escono in JPG anche da un PNG, e dichiarare il
        // tipo dell'originale su un `-detail.jpg` è una riga falsa.
        $xml->writeAttribute('type', $this->tipoDellImmagine($perIlWeb, $immagine->mime_type));
        $xml->endElement();
    }

    /**
     * Il media type dedotto dall'estensione dell'indirizzo, con il tipo
     * dichiarato dal media come ripiego.
     */
    private function tipoDellImmagine(string $indirizzo, ?string $ripiego): string
    {
        $percorso = parse_url($indirizzo, PHP_URL_PATH);
        $estensione = strtolower(pathinfo(is_string($percorso) ? $percorso : '', PATHINFO_EXTENSION));

        return match ($estensione) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            default => $ripiego ?: 'image/jpeg',
        };
    }

    /**
     * Il testo della `description`: l'occhiello scritto in redazione, o le
     * prime righe del contenuto quando manca.
     */
    private function sommario(Post $notizia, string $locale, string $contenuto): string
    {
        $occhiello = trim(strip_tags($notizia->testoTradotto('excerpt', $locale)));

        if ($occhiello === '') {
            $occhiello = strip_tags($contenuto);
        }

        return $this->ripulito(Str::of($occhiello)->squish()->limit(400)->toString());
    }

    /**
     * Rende assoluti gli indirizzi scritti relativi dentro il contenuto: un
     * `/storage/news/...` letto dentro un lettore RSS punterebbe al dominio
     * dell'aggregatore. I `//host/...` restano come sono.
     */
    private function conIndirizziAssoluti(string $html): string
    {
        return (string) preg_replace_callback(
            '#\b(src|href)="/(?!/)([^"]*)"#i',
            fn (array $pezzi) => $pezzi[1].'="'.url('/'.$pezzi[2]).'"',
            $html
        );
    }

    /**
     * Il prefisso dei nomi di rotta della lingua chiesta.
     *
     * Si chiede al router se quel nome esiste invece di confrontare la lingua
     * con `config('app.locale')`: `app()->setLocale()` riscrive proprio quella
     * voce di configurazione, quindi durante una richiesta a `/en/feed` il
     * confronto risulta vero e il feed inglese uscirebbe con gli indirizzi
     * italiani.
     */
    private static function prefissoDeiNomi(string $locale): string
    {
        return Route::has($locale.'.news.index') ? $locale.'.' : '';
    }

    /**
     * Spezza le sequenze `]]>` che chiuderebbero il CDATA a metà testo:
     * `XMLWriter::writeCdata()` non le tratta, e basta un commento della
     * redazione che le contenga per troncare il feed.
     */
    private function perIlCdata(string $testo): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $testo);
    }

    /**
     * Toglie i caratteri che l'XML non ammette. I comunicati importati da
     * WordPress ne contengono (a capo verticali, caratteri di controllo): uno
     * solo rende il feed illeggibile a qualunque aggregatore.
     */
    private function ripulito(string $testo): string
    {
        return (string) preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u',
            '',
            $testo
        );
    }

    private function copyright(): string
    {
        return '© '.date('Y').' Savino Del Bene Volley';
    }
}
