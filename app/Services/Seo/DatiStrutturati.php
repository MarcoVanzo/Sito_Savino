<?php

namespace App\Services\Seo;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\PageController;
use App\Models\Game;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Roster;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Support\MatchDay;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * I dati strutturati (schema.org, JSON-LD) delle pagine pubbliche.
 *
 * Si calcolano sul server e finiscono nell'HTML del layout (app.blade.php):
 * senza SSR, un blocco scritto da Vue esisterebbe solo dopo il JavaScript.
 * Ogni metodo restituisce un array pronto per `json_encode`; chi chiama
 * sceglie quali blocchi servono alla pagina (ServeSocialCrawlerMeta).
 *
 * Solo dati veri: niente recensioni né voti inventati, niente data di nascita
 * (in archivio vale quasi sempre 1° gennaio), offerte solo dove c'è un link
 * di vendita.
 */
class DatiStrutturati
{
    public const NOME = 'Savino Del Bene Volley';

    /** La voce di Wikipedia della società. */
    public const WIKIPEDIA = 'https://it.wikipedia.org/wiki/Pallavolo_Scandicci_Savino_Del_Bene';

    /** Il PalaBigMat si chiamava Palazzo Wanny: la CEV usa ancora quel nome. */
    private const NOMI_PRECEDENTI_DEL_PALAZZETTO = ['Palazzo Wanny'];

    /** Lunghezza massima di `headline` per Google. */
    private const HEADLINE_MAX = 110;

    /**
     * Il palazzetto letto una volta per richiesta (un calendario ha decine di
     * gare); `false` = non ancora letto.
     *
     * @var array<string, mixed>|false|null
     */
    private array|false|null $palazzetto = false;

    /**
     * L'id della prossima gara in casa, letto una volta per richiesta: il
     * calendario chiede `eLaProssimaInCasa()` per ogni gara della società
     * (N+1 segnalato da Sentry su /stagione/risultati il 15/10/2026).
     * `false` = non ancora letto.
     */
    private int|false|null $prossimaGaraInCasa = false;

    /** @var list<string>|null */
    private ?array $cittaDellaLega = null;

    /** Link di vendita del Match Day o della Biglietteria; `false` = non ancora letto. */
    private string|false|null $linkDeiBiglietti = false;

    /** Indirizzo della pagina Biglietteria; `false` = non ancora letto. */
    private string|false|null $paginaBiglietteria = false;

    /**
     * La società: solo in home, con i profili ufficiali in `sameAs`.
     *
     * @return array<string, mixed>
     */
    public function squadra(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SportsTeam',
            '@id' => url('/').'#squadra',
            'name' => self::NOME,
            'alternateName' => 'Pallavolo Scandicci Savino Del Bene',
            'sport' => 'Volleyball',
            'gender' => 'https://schema.org/Female',
            'url' => url('/'),
            'logo' => $this->logo(),
            'location' => $this->luogoDiCasa(),
            'memberOf' => [
                '@type' => 'SportsOrganization',
                'name' => 'Lega Pallavolo Serie A Femminile',
                'url' => (string) config('services.lvf.base_url'),
            ],
            'sameAs' => $this->profiliUfficiali(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sito(string $locale): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => self::NOME,
            'url' => url('/'),
            'inLanguage' => $locale === 'en' ? 'en-US' : 'it-IT',
            'publisher' => $this->editore(),
        ];
    }

    /**
     * Una partita. `offers` solo per le gare in casa non giocate con un link
     * di vendita (vedi offertaBiglietti): un'offerta senza biglietti in
     * vendita sarebbe un dato falso.
     *
     * @return array<string, mixed>|null
     */
    public function gara(Game $gara, string $locale): ?array
    {
        $gara->loadMissing(['homeTeam.media', 'awayTeam.media']);

        if (! $gara->homeTeam || ! $gara->awayTeam) {
            return null;
        }

        $casa = $gara->homeTeam->nomePubblico();
        $ospite = $gara->awayTeam->nomePubblico();

        $evento = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $casa.' - '.$ospite,
            'sport' => 'Volleyball',
            'url' => $this->indirizzoDellaGara($gara, $locale),
            'startDate' => $gara->match_date->toIso8601String(),
            // L'orario di fine non lo dà nessuno: la gara si chiude entro la
            // mezzanotte del suo giorno.
            'endDate' => $gara->match_date->copy()->endOfDay()->toIso8601String(),
            'eventStatus' => $this->statoDellEvento($gara),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $this->luogo($gara),
            'homeTeam' => $this->squadraDellaGara($gara->homeTeam),
            'awayTeam' => $this->squadraDellaGara($gara->awayTeam),
            'competitor' => [$this->squadraDellaGara($gara->homeTeam), $this->squadraDellaGara($gara->awayTeam)],
            'organizer' => $this->organizzatore($gara),
            'image' => array_values(array_filter([
                $gara->homeTeam->logoUrl(),
                $gara->awayTeam->logoUrl(),
                $this->logo(),
            ])),
        ];

        // La competizione va nella descrizione, non in un `superEvent`:
        // Google legge un evento annidato come un secondo evento, senza data
        // né luogo, e lo segnala come non valido.
        $evento['description'] = $this->descrizioneDellaGara($gara, $casa, $ospite);
        $evento['performer'] = $evento['competitor'];

        $offerta = $this->offertaBiglietti($gara);

        if ($offerta !== null) {
            $evento['offers'] = $offerta;
        }

        return $evento;
    }

    private function descrizioneDellaGara(Game $gara, string $casa, string $ospite): string
    {
        $parti = array_filter([
            $gara->competition_type?->getLabel(),
            $gara->matchday ? $gara->matchday.'ª giornata' : null,
        ]);
        $descrizione = $casa.' - '.$ospite.($parti !== [] ? ', '.implode(', ', $parti) : '');

        if ($gara->status === GameStatus::Completed && $gara->home_score !== null && $gara->away_score !== null) {
            $descrizione .= ': '.$gara->home_score.'-'.$gara->away_score;
        }

        return $descrizione.'.';
    }

    /**
     * La scheda della partita esiste solo per le gare giocate con il
     * tabellino (RisultatiController::partita); le altre rimandano al
     * calendario della loro competizione.
     */
    private function indirizzoDellaGara(Game $gara, string $locale): string
    {
        $haLaScheda = $gara->status === GameStatus::Completed
            && $gara->home_score !== null
            && $gara->away_score !== null
            && ($gara->player_stats_exists ?? $gara->playerStats()->exists());

        if ($haLaScheda) {
            return route($this->prefisso($locale).'stagione.partita', ['game' => $gara->id]);
        }

        $calendario = match ($gara->competition_type) {
            CompetitionType::ChampionsLeague => 'stagione.cev',
            CompetitionType::CoppaItalia => 'stagione.coppa-italia',
            CompetitionType::Playoff => 'stagione.playoff',
            default => 'stagione.risultati',
        };

        return route($this->prefisso($locale).$calendario);
    }

    /**
     * Le gare della società fra quelle di una pagina di calendario.
     *
     * @param  iterable<Game>  $gare
     * @return list<array<string, mixed>>
     */
    public function gare(iterable $gare, string $locale): array
    {
        $blocchi = [];

        foreach ($gare as $gara) {
            if ($gara->homeTeam?->is_internal || $gara->awayTeam?->is_internal) {
                $blocco = $this->gara($gara, $locale);

                if ($blocco !== null) {
                    $blocchi[] = $blocco;
                }
            }
        }

        return $blocchi;
    }

    /**
     * @return array<string, mixed>
     */
    public function notizia(Post $post, string $locale): array
    {
        $titolo = trim((string) $post->getTranslation('title', $locale));
        $descrizione = trim((string) ($post->getTranslation('meta_description', $locale) ?: $post->getTranslation('excerpt', $locale)));
        $indirizzo = route($this->prefisso($locale).'news.show', ['slug' => $post->slug]);
        // L'originale e non una conversione: Google chiede almeno 1200 px di
        // larghezza, e `card`/`detail` stanno sotto o al limite.
        $immagine = $post->getFirstMediaUrl('cover') ?: $this->logo();

        $articolo = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => Str::limit($titolo, self::HEADLINE_MAX - 1, '…'),
            'url' => $indirizzo,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $indirizzo],
            'image' => [$immagine],
            'datePublished' => ($post->published_at ?? $post->created_at)?->toIso8601String(),
            'dateModified' => ($post->updated_at ?? $post->published_at)?->toIso8601String(),
            'author' => $this->autore($post),
            'publisher' => $this->editore(),
            'inLanguage' => $locale === 'en' ? 'en-US' : 'it-IT',
        ];

        if ($descrizione !== '') {
            $articolo['description'] = Str::limit(trim(strip_tags($descrizione)), 300);
        }

        return $articolo;
    }

    /**
     * @return array<string, mixed>
     */
    public function prodotto(Product $prodotto, string $locale): array
    {
        $nome = trim((string) $prodotto->getTranslation('name', $locale));
        $descrizione = trim(strip_tags((string) ($prodotto->getTranslation('short_description', $locale) ?: $prodotto->getTranslation('description', $locale))));
        $indirizzo = route($this->prefisso($locale).'shop.product', ['product' => $prodotto->slug]);

        $immagini = $prodotto->getMedia('products')->concat($prodotto->getMedia('images'))
            ->map(fn ($media) => $media->getUrl())
            ->unique()
            ->values()
            ->all();

        $scheda = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $nome,
            'url' => $indirizzo,
            'image' => $immagini !== [] ? $immagini : [$this->logo()],
            'brand' => ['@type' => 'Brand', 'name' => self::NOME],
            'offers' => [
                '@type' => 'Offer',
                'url' => $indirizzo,
                'price' => number_format($prodotto->effectivePrice(), 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability' => $prodotto->availableStock() > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => $prodotto->usato_o_autografato
                    ? 'https://schema.org/UsedCondition'
                    : 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'Organization', 'name' => self::NOME],
            ],
        ];

        if ($descrizione !== '') {
            $scheda['description'] = Str::limit($descrizione, 500);
        }

        if (filled($prodotto->sku)) {
            $scheda['sku'] = (string) $prodotto->sku;
        }

        return $scheda;
    }

    /**
     * L'elenco dei prodotti di una categoria, nell'ordine di vetrina.
     *
     * @param  Collection<int, Product>  $prodotti
     * @return array<string, mixed>
     */
    public function categoria(ProductCategory $categoria, Collection $prodotti, string $locale): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => trim((string) $categoria->getTranslation('name', $locale)),
            'url' => route($this->prefisso($locale).'shop.category', ['category' => $categoria->slug]),
            'numberOfItems' => $prodotti->count(),
            'itemListElement' => $prodotti->values()->map(fn (Product $prodotto, int $indice) => [
                '@type' => 'ListItem',
                'position' => $indice + 1,
                'url' => route($this->prefisso($locale).'shop.product', ['product' => $prodotto->slug]),
                'name' => trim((string) $prodotto->getTranslation('name', $locale)),
            ])->all(),
        ];
    }

    /**
     * Un'atleta della prima squadra.
     *
     * @return array<string, mixed>
     */
    public function atleta(Roster $riga, string $ruolo, string $indirizzo): array
    {
        $giocatrice = $riga->player;

        $persona = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => (string) $giocatrice?->full_name,
            'givenName' => (string) $giocatrice?->first_name,
            'familyName' => (string) $giocatrice?->last_name,
            'url' => $indirizzo,
            'jobTitle' => $ruolo,
            'memberOf' => [
                '@type' => 'SportsTeam',
                '@id' => url('/').'#squadra',
                'name' => self::NOME,
                'url' => url('/'),
            ],
        ];

        if ($riga->official_photo_url) {
            $persona['image'] = $riga->official_photo_url;
        }

        if (filled($giocatrice?->nationality)) {
            $persona['nationality'] = (string) $giocatrice->nationality;
        }

        if ($riga->height_cm) {
            $persona['height'] = [
                '@type' => 'QuantitativeValue',
                'value' => $riga->height_cm,
                'unitCode' => 'CMT',
            ];
        }

        $profili = array_values(array_filter([
            $giocatrice?->instagramUrl(),
            $giocatrice?->wikipedia_title
                ? 'https://'.($giocatrice->wikipedia_lang ?: 'it').'.wikipedia.org/wiki/'.str_replace(' ', '_', $giocatrice->wikipedia_title)
                : null,
        ]));

        if ($profili !== []) {
            $persona['sameAs'] = $profili;
        }

        return $persona;
    }

    /**
     * Le briciole di pane: la home e poi una voce per livello. L'ultima è la
     * pagina stessa, che per Google può restare senza indirizzo.
     *
     * @param  list<array{name: string, url: ?string}>  $voci
     * @return array<string, mixed>|null
     */
    public function briciole(array $voci): ?array
    {
        $voci = array_values(array_filter($voci, fn (array $voce) => trim($voce['name']) !== ''));

        if (count($voci) < 2) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $voce, int $indice) => array_filter([
                '@type' => 'ListItem',
                'position' => $indice + 1,
                'name' => $voce['name'],
                'item' => $voce['url'],
            ], fn ($valore) => $valore !== null), $voci, array_keys($voci)),
        ];
    }

    /**
     * Il prefisso dei nomi di rotta di una lingua (`en.`), vuoto per quella
     * predefinita.
     */
    private function prefisso(string $locale): string
    {
        return $locale === config('app.fallback_locale') ? '' : $locale.'.';
    }

    private function logo(): string
    {
        return url('/images/logo.png');
    }

    /**
     * @return array<string, mixed>
     */
    private function editore(): array
    {
        return [
            '@type' => 'Organization',
            'name' => self::NOME,
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $this->logo(),
            ],
        ];
    }

    /**
     * L'autore della notizia: la persona se la redazione l'ha firmata, la
     * società altrimenti (le notizie importate non hanno autore).
     *
     * @return array<string, mixed>
     */
    private function autore(Post $post): array
    {
        $post->loadMissing('author');
        $nome = trim((string) $post->author?->name);

        return $nome !== ''
            ? ['@type' => 'Person', 'name' => $nome]
            : ['@type' => 'Organization', 'name' => self::NOME, 'url' => url('/')];
    }

    /**
     * Facebook, Instagram e YouTube dalle impostazioni, più Wikipedia e la
     * scheda della società sul sito della Lega.
     *
     * @return list<string>
     */
    private function profiliUfficiali(): array
    {
        $profili = [];

        foreach (['social_facebook', 'social_instagram', 'social_youtube'] as $chiave) {
            $indirizzo = trim((string) SiteSetting::get($chiave, ''));

            if (str_starts_with($indirizzo, 'https://')) {
                $profili[] = $indirizzo;
            }
        }

        $profili[] = self::WIKIPEDIA;

        // La Lega rinumera le società a ogni stagione: il primo identificativo
        // è quello della stagione in corso (CLAUDE.md §12).
        $club = config('services.lvf.club_ids')[0] ?? null;

        if ($club) {
            $profili[] = rtrim((string) config('services.lvf.base_url'), '/').'/club/savino-del-bene-scandicci/'.$club.'/';
        }

        return $profili;
    }

    /**
     * Il palazzetto di casa, come lo scrive la Lega nelle gare interne.
     *
     * @return array<string, mixed>
     */
    private function luogoDiCasa(): array
    {
        $palazzetto = $this->palazzettoDiCasa();

        if ($palazzetto !== null) {
            return $palazzetto;
        }

        $gara = Game::query()
            ->whereNotNull('location')
            ->whereHas('homeTeam', fn ($query) => $query->where('is_internal', true))
            ->latest('match_date')
            ->first();

        return $gara !== null ? $this->luogo($gara) : [
            '@type' => 'Place',
            'name' => 'Scandicci',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Scandicci', 'addressRegion' => 'FI', 'addressCountry' => 'IT'],
        ];
    }

    /**
     * Il luogo della gara. La Lega lo scrive come «Impianto - Città», la CEV
     * come «Impianto CITTÀ» (la città in maiuscolo, in fondo o in testa). Il
     * paese si dichiara solo se si sa: dal fuso orario della città (lo
     * stesso elenco con cui si convertono gli orari CEV), o Italia per le
     * gare della Lega e per le città in cui la Lega gioca. Una città ignota
     * resta senza paese: meglio niente che l'Italia per Obrenovac.
     *
     * @return array<string, mixed>
     */
    private function luogo(Game $gara): array
    {
        $luogo = trim((string) $gara->location);
        $palazzetto = $this->palazzettoDiCasa();

        // Il Pala BigMat ha lo stesso indirizzo anche quando in casa è
        // l'altra squadra di Firenze.
        if ($palazzetto !== null && (
            ($luogo === '' && $gara->homeTeam?->is_internal)
            || ($luogo !== '' && $this->eIlPalazzettoDiCasa($luogo, (string) $palazzetto['name']))
        )) {
            return $palazzetto;
        }

        if ($luogo === '') {
            return ['@type' => 'Place', 'name' => (string) $gara->homeTeam?->nomePubblico()];
        }

        [$impianto, $citta] = $gara->cev_match_id !== null
            ? $this->impiantoECittaCev($luogo)
            : $this->impiantoECittaLega($luogo);

        $indirizzo = ['@type' => 'PostalAddress'];

        if ($citta !== '') {
            $indirizzo['addressLocality'] = Str::title(mb_strtolower($citta));
        }

        $paese = $this->paeseDellaCitta($citta, $gara->cev_match_id === null);

        if ($paese !== null) {
            $indirizzo['addressCountry'] = $paese;
        }

        return [
            '@type' => 'Place',
            'name' => $impianto,
            'address' => $indirizzo,
        ];
    }

    /**
     * «Impianto - Città»: la città è dopo l'ultimo trattino.
     *
     * @return array{0: string, 1: string}
     */
    private function impiantoECittaLega(string $luogo): array
    {
        $parti = preg_split('/\s+-\s+/', $luogo) ?: [$luogo];
        $citta = count($parti) > 1 ? trim((string) array_pop($parti)) : '';

        return [trim(implode(' - ', $parti)), $citta];
    }

    /**
     * «Eczacibasi Spor Salonu ISTANBUL», «TOVUZ Olympic Hall»: la città è la
     * fila di parole tutte maiuscole in fondo (o, se manca, in testa). Le
     * sigle senza vocali in testa alla fila («SKC OBRENOVAC») non sono città.
     * Il nome dell'impianto resta quello scritto dalla CEV.
     *
     * @return array{0: string, 1: string}
     */
    private function impiantoECittaCev(string $luogo): array
    {
        $parole = preg_split('/\s+/u', $luogo) ?: [];
        $maiuscola = fn (string $parola): bool => preg_match('/^\p{Lu}[\p{Lu}\-\']+$/u', $parola) === 1;

        $fila = [];

        for ($i = count($parole) - 1; $i >= 0 && $maiuscola($parole[$i]); $i--) {
            array_unshift($fila, $parole[$i]);
        }

        if ($fila === []) {
            for ($i = 0; $i < count($parole) && $maiuscola($parole[$i]); $i++) {
                $fila[] = $parole[$i];
            }
        }

        // Tutto maiuscolo: non si sa dove finisca l'impianto.
        if (count($fila) === count($parole)) {
            return [$luogo, ''];
        }

        while (count($fila) > 1 && preg_match('/[AEIOUYÀ-ÿ]/iu', $fila[0]) !== 1) {
            array_shift($fila);
        }

        return [$luogo, implode(' ', $fila)];
    }

    /**
     * Il palazzetto con l'indirizzo completo, dalla pagina Palazzetto del CMS
     * (`venue_name`, `venue_address` «Via …, civico — CAP Città (PR)»). Null
     * se la pagina non c'è o non ha i due campi.
     *
     * @return array<string, mixed>|null
     */
    private function palazzettoDiCasa(): ?array
    {
        if ($this->palazzetto !== false) {
            return $this->palazzetto;
        }

        $dati = $this->datiDellaPagina('palazzetto');
        $nome = trim((string) ($dati['venue_name'] ?? ''));
        $indirizzo = trim((string) ($dati['venue_address'] ?? ''));

        if ($nome === '' || $indirizzo === '') {
            return $this->palazzetto = null;
        }

        $postale = ['@type' => 'PostalAddress'];

        if (preg_match('/^(.+?)\s+[—–-]\s+(\d{5})\s+(.+?)(?:\s*\((\w{2})\))?$/u', $indirizzo, $parti) === 1) {
            $postale['streetAddress'] = trim($parti[1]);
            $postale['postalCode'] = $parti[2];
            $postale['addressLocality'] = trim($parti[3]);

            if (($parti[4] ?? '') !== '') {
                $postale['addressRegion'] = mb_strtoupper($parti[4]);
            }
        } else {
            $postale['streetAddress'] = $indirizzo;
        }

        $postale['addressCountry'] = 'IT';

        return $this->palazzetto = [
            '@type' => 'Place',
            'name' => $nome,
            'address' => $postale,
        ];
    }

    /**
     * La Lega scrive il palazzetto col nome di oggi («Pala BigMat - Firenze»),
     * la CEV ancora con quello di prima («Palazzo Wanny FIRENZE»).
     */
    private function eIlPalazzettoDiCasa(string $luogo, string $nome): bool
    {
        $normalizza = fn (string $testo): string => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($testo))) ?? '';
        $luogo = $normalizza($luogo);

        foreach ([$nome, ...self::NOMI_PRECEDENTI_DEL_PALAZZETTO] as $candidato) {
            $candidato = $normalizza($candidato);

            if ($candidato !== '' && str_contains($luogo, $candidato)) {
                return true;
            }
        }

        return false;
    }

    private function paeseDellaCitta(string $citta, bool $garaDellaLega): ?string
    {
        if ($garaDellaLega) {
            return 'IT';
        }

        $maiuscola = mb_strtoupper(trim($citta));

        if ($maiuscola === '') {
            return null;
        }

        foreach ((array) config('services.cev.fusi_orari', []) as $fuso => $citta_del_fuso) {
            if (in_array($maiuscola, (array) $citta_del_fuso, true)) {
                $codice = (new DateTimeZone((string) $fuso))->getLocation()['country_code'] ?? null;

                return is_string($codice) && $codice !== '??' ? $codice : null;
            }
        }

        return in_array($maiuscola, $this->cittaDellaLega(), true) ? 'IT' : null;
    }

    /**
     * Le città in cui si giocano le gare della Lega (tutte in Italia), lette
     * una volta per richiesta: una gara CEV a Novara o a Treviso è in Italia.
     *
     * @return list<string>
     */
    private function cittaDellaLega(): array
    {
        if ($this->cittaDellaLega !== null) {
            return $this->cittaDellaLega;
        }

        return $this->cittaDellaLega = Game::query()
            ->whereNull('cev_match_id')
            ->whereNotNull('location')
            ->distinct()
            ->pluck('location')
            ->map(fn ($luogo) => mb_strtoupper($this->impiantoECittaLega(trim((string) $luogo))[1]))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function squadraDellaGara(Team $squadra): array
    {
        $nodo = [
            '@type' => 'SportsTeam',
            'name' => $squadra->nomePubblico(),
            'sport' => 'Volleyball',
        ];

        if ($squadra->is_internal) {
            $nodo['url'] = url('/');
        }

        $logo = $squadra->logoUrl();

        if ($logo) {
            $nodo['logo'] = $logo;
        }

        return $nodo;
    }

    /**
     * @return array<string, mixed>
     */
    private function organizzatore(Game $gara): array
    {
        if ($gara->cev_match_id !== null) {
            return ['@type' => 'SportsOrganization', 'name' => 'CEV - Confédération Européenne de Volleyball', 'url' => 'https://www.cev.eu'];
        }

        return ['@type' => 'SportsOrganization', 'name' => 'Lega Pallavolo Serie A Femminile', 'url' => (string) config('services.lvf.base_url')];
    }

    private function statoDellEvento(Game $gara): string
    {
        return $gara->status === GameStatus::Postponed
            ? 'https://schema.org/EventPostponed'
            : 'https://schema.org/EventScheduled';
    }

    /**
     * I biglietti si vendono su Vivaticket. Con il link nella scheda della
     * gara (Gare → Biglietti) l'offerta vale per qualunque gara in casa non
     * ancora giocata; senza, solo per la prossima, l'unica di cui si sa che è
     * in vendita, con il link del Match Day o della Biglietteria. Prezzo e
     * data d'inizio vendita solo se la redazione li ha scritti: mai inventati.
     * Prima dell'inizio vendita l'offerta è `PreOrder`, non `InStock`.
     *
     * @return array<string, mixed>|null
     */
    private function offertaBiglietti(Game $gara): ?array
    {
        if (! $gara->homeTeam?->is_internal
            || $gara->status !== GameStatus::Scheduled
            || ! $gara->match_date->isFuture()) {
            return null;
        }

        $linkDellaGara = $this->linkSicuro($gara->biglietti_url);
        $prossima = $this->eLaProssimaInCasa($gara);

        if ($linkDellaGara === null && ! $prossima) {
            return null;
        }

        // Il link del Match Day è quello della gara del giorno: se manca si
        // ripiega sulla pagina Biglietteria.
        $link = $linkDellaGara ?? $this->linkDeiBiglietti() ?? $this->paginaBiglietteria();

        if ($link === null) {
            return null;
        }

        $inVenditaDal = $gara->biglietti_in_vendita_dal;

        $offerta = [
            '@type' => 'Offer',
            'url' => $link,
            'availability' => match (true) {
                $gara->biglietti_esauriti => 'https://schema.org/SoldOut',
                $inVenditaDal !== null && $inVenditaDal->isFuture() => 'https://schema.org/PreOrder',
                default => 'https://schema.org/InStock',
            },
            'validThrough' => $gara->match_date->toIso8601String(),
        ];

        if ($inVenditaDal !== null) {
            $offerta['validFrom'] = $inVenditaDal->toIso8601String();
        }

        if ($gara->biglietti_prezzo_da !== null) {
            $offerta['price'] = number_format((float) $gara->biglietti_prezzo_da, 2, '.', '');
            $offerta['priceCurrency'] = 'EUR';
        }

        return $offerta;
    }

    private function eLaProssimaInCasa(Game $gara): bool
    {
        if ($this->prossimaGaraInCasa === false) {
            $id = Game::query()
                ->where('status', GameStatus::Scheduled)
                ->where('match_date', '>=', now())
                ->whereHas('homeTeam', fn ($query) => $query->where('is_internal', true))
                ->orderBy('match_date')
                ->value('id');
            $this->prossimaGaraInCasa = $id === null ? null : (int) $id;
        }

        return $this->prossimaGaraInCasa === $gara->id;
    }

    private function linkSicuro(?string $link): ?string
    {
        $link = trim((string) $link);

        return str_starts_with($link, 'https://') ? $link : null;
    }

    private function linkDeiBiglietti(): ?string
    {
        if ($this->linkDeiBiglietti !== false) {
            return $this->linkDeiBiglietti;
        }

        $link = $this->linkSicuro((string) SiteSetting::get('match_day_popup_url', ''));

        if ($link === null) {
            $dati = $this->datiDellaPagina('biglietteria');
            $link = $this->linkSicuro((string) ($dati['tickets_url'] ?? ''));
        }

        return $this->linkDeiBiglietti = $link;
    }

    /**
     * La pagina Biglietteria del sito, se è pubblicata.
     */
    private function paginaBiglietteria(): ?string
    {
        if ($this->paginaBiglietteria !== false) {
            return $this->paginaBiglietteria;
        }

        $percorso = Page::where('slug', 'biglietteria')->where('status', PostStatus::Published)->exists()
            ? PageController::percorsoPubblico('biglietteria')
            : null;

        return $this->paginaBiglietteria = $percorso !== null ? url($percorso) : null;
    }

    /**
     * Il `content_data` della lingua predefinita di una pagina pubblicata.
     *
     * @return array<string, mixed>
     */
    private function datiDellaPagina(string $slug): array
    {
        $pagina = Page::where('slug', $slug)
            ->where('status', PostStatus::Published)
            ->first();
        $dati = $pagina?->getTranslation('content_data', (string) config('app.fallback_locale'));
        $dati = is_string($dati) ? json_decode($dati, true) : $dati;

        return is_array($dati) ? $dati : [];
    }

    /**
     * La gara da annunciare in home: quella di oggi se c'è, altrimenti la
     * prossima della società.
     */
    public function garaDellaHome(): ?Game
    {
        return MatchDay::garaDiOggi() ?? MatchDay::prossimaGara();
    }
}
