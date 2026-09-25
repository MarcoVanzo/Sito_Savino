<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Enums\PostStatus;
use App\Enums\StaffType;
use App\Models\Game;
use App\Models\Page;
use App\Models\Roster;
use App\Models\Season;
use App\Models\ShippingZone;
use App\Models\StaffMember;
use App\Models\Team;
use App\Services\SponsorDirectory;
use App\Support\DichiarazioneCookie;
use App\Support\PagineLegaliDelloShop;
use App\Support\PermalinkVecchioSito;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PageController extends Controller
{
    /**
     * Template consentiti per il rendering Inertia.
     * Previene code injection da valori malevoli nel database.
     * Deve corrispondere ai componenti Vue in resources/js/Pages/Public/.
     */
    private const ALLOWED_TEMPLATES = [
        'Public/ContentPage',
        'Public/Stagione',
        'Public/Home',
        'Public/Societa/Organigramma',
        'Public/Societa/Storia',
        'Public/Societa/Palazzetto',
        'Public/Societa/Safeguarding',
        'Public/Roster',

        'Public/Ticketing',
        'Public/ClubRace',
        'Public/Convenzioni',
        'Public/Sponsor',
        'Public/Youth',
        'Public/Affiliazioni',
        'Public/SummerCamp',
        'Public/TalentDay',
        'Public/Sociale',
        'Public/Comunicazione',
        'Public/Risultati',
        'Public/Gallery',
        'Public/Staff',
        'Public/Contatti',
    ];

    /**
     * Mappatura dei singoli slug di pagina alle rispettive sezioni (per URL canonici SEO).
     */
    /**
     * L'indirizzo pubblico di una pagina del CMS a partire dallo slug: quello
     * della sua sezione, non `/{slug}` che per le pagine di sezione è solo un
     * rimando. Serve alla sitemap, che pubblicava 24 indirizzi su 38 in 301.
     * Null per le pagine che non hanno un indirizzo proprio.
     */
    public static function percorsoPubblico(string $slug): ?string
    {
        // Pagine-contenitore: l'indirizzo è una rotta sua (già in sitemap) o un
        // rimando alla prima pagina della sezione.
        if (in_array($slug, ['home', 'societa', 'sponsor', 'shop', 'comunicazione'], true)) {
            return null;
        }

        if (! isset(self::SLUG_SECTION_MAP[$slug])) {
            return '/'.$slug;
        }

        $sezione = self::SLUG_SECTION_MAP[$slug];

        // Slug uguale alla sezione: l'indirizzo è la sezione (`/summer-camp`).
        return $slug === $sezione ? '/'.$sezione : '/'.$sezione.'/'.$slug;
    }

    private const SLUG_SECTION_MAP = [
        // Società
        'organigramma' => 'societa',
        'storia' => 'societa',
        'safeguarding' => 'societa',
        'palazzetto' => 'societa',

        // Ticketing
        'club-race' => 'ticketing',
        'abbonamenti' => 'ticketing',
        'biglietteria' => 'ticketing',
        'convenzioni' => 'ticketing',
        'accessibilita' => 'ticketing',

        // Sponsor
        'diventa-sponsor' => 'sponsor',
        'title-sponsor' => 'sponsor',
        'hospitality' => 'sponsor',

        // Youth
        'settore-giovanile' => 'youth',
        'talent-day' => 'youth',
        'affiliazioni' => 'youth',

        // Summer Camp
        'summer-camp' => 'summer-camp',
        'iscrizione-experience' => 'summer-camp',

        // Sociale
        'volley-4-all' => 'sociale',
        'progetti-sociali' => 'sociale',
        'sostenibilita' => 'sociale',
        'progetto-scuola' => 'sociale',

        // Comunicazione
        'accrediti-stampa' => 'comunicazione',
        'cartelle-stampa' => 'comunicazione',
        'double-face' => 'comunicazione',
        'magazine' => 'comunicazione',
    ];

    public function show($slug)
    {
        $page = Page::where('slug', $slug)
            ->where('status', PostStatus::Published)
            ->with('media')
            ->first();

        if (! $page) {
            // Prima del 404: su WordPress le notizie stavano alla radice del
            // dominio, dove oggi risponde la rotta generica. Se quello slug è
            // una notizia pubblicata, il posto giusto è `/news/{slug}`. Si
            // guarda qui e non in una rotta a parte perché la generica è
            // l'unica che può ricevere un indirizzo a un segmento, e si guarda
            // dopo il CMS perché una pagina e un vecchio comunicato possono
            // avere lo stesso slug: vince la pagina.
            //
            // Solo dalla generica, però: questo metodo serve anche le sezioni
            // (`/societa/{slug}`, `/youth/{slug}`), e lì un indirizzo sbagliato
            // è un indirizzo sbagliato — il vecchio sito non ci ha mai messo
            // le notizie sotto.
            $permalink = request()->routeIs('*pages.show')
                ? PermalinkVecchioSito::perLoSlug((string) $slug)
                : null;

            if ($permalink !== null) {
                return redirect()->to($permalink, 301);
            }

            abort(404);
        }

        // La pagina "home" del CMS tiene i metadati della homepage: aperta dal
        // suo slug disegnava una home svuotata (niente slide, gara, notizie),
        // ed era pure in sitemap. Il suo indirizzo è "/".
        if ($page->slug === 'home') {
            return redirect()->route(app()->getLocale() === config('app.fallback_locale') ? 'home' : app()->getLocale().'.home', [], 301);
        }

        // Espone ai template pubblici la copertina (hero) e le foto della
        // galleria di pagina, entrambe gestite dal pannello.
        $page->append(['cover_url', 'gallery_images']);

        $canonico = $this->redirectCanonico($page);

        if ($canonico !== null) {
            return $canonico;
        }

        // Se il template è nella whitelist, usalo. Altrimenti renderizza
        // la pagina generica con un layout che mostra il contenuto della page.
        $template = $page->template && in_array($page->template, self::ALLOWED_TEMPLATES)
            ? $page->template
            : 'Public/ContentPage'; // Fallback generico che renderizza il contenuto

        // Props aggiuntive per template specifici
        $extra = $this->getTemplateData($template);

        // La Cookie Policy non elenca a mano i cookie: mostra quelli che la
        // scansione settimanale ha trovato davvero sul sito. Si riconosce dallo
        // slug e non dal template, perché divide `Public/ContentPage` con le
        // altre pagine di solo testo.
        if ($page->slug === 'cookie-policy') {
            $extra['dichiarazioneCookie'] = DichiarazioneCookie::perIlFrontend();
        }

        // La pagina Spedizioni non scrive a mano costi e tempi: li legge dalle
        // zone di spedizione del pannello, le stesse con cui il checkout fa il
        // conto. Due copie avrebbero finito per dire cose diverse, come gia'
        // succedeva sul vecchio negozio (24-48 ore in una pagina, 48-72
        // nell'altra).
        if ($page->slug === PagineLegaliDelloShop::SPEDIZIONI) {
            $extra['zoneDiSpedizione'] = ShippingZone::active()->ordered()->get()
                ->map(fn (ShippingZone $zona) => [
                    'nome' => $zona->getTranslation('name', app()->getLocale()),
                    'paesi' => $zona->countries,
                    'tariffa' => (float) $zona->flat_rate,
                    'fasce' => $zona->fasceOrdinate(),
                    'soglia_gratuita' => $zona->free_threshold !== null ? (float) $zona->free_threshold : null,
                    'giorni_min' => $zona->estimated_days_min,
                    'giorni_max' => $zona->estimated_days_max,
                ])->values()->all();
        }

        // I file caricati dal pannello dentro `content_data` (press kit,
        // magazine, immagini dei pulsanti) diventano indirizzi pubblici veri:
        // i template li usavano come "/storage/{percorso}", che in produzione
        // — con i file su Spaces — non porta da nessuna parte.
        return Inertia::render($template, array_merge([
            'page' => $page->datiPerIlFrontend(),
        ], $extra));
    }

    /**
     * Il redirect 301 all'indirizzo canonico, quando la pagina e' stata
     * raggiunta da una rotta diversa da quello.
     *
     * Serve a non avere lo stesso contenuto su due indirizzi (SEO): la pagina
     * Contatti ha un indirizzo suo, e le pagine di sezione raggiunte dalla
     * rotta generica vanno riportate alla loro sezione.
     */
    private function redirectCanonico(Page $page): ?RedirectResponse
    {
        $routePrefix = app()->getLocale() === 'it' ? '' : app()->getLocale().'.';

        if ($page->slug === 'contatti') {
            return redirect()->route($routePrefix.'contatti', [], 301);
        }

        if (! isset(self::SLUG_SECTION_MAP[$page->slug])) {
            return null;
        }

        $section = self::SLUG_SECTION_MAP[$page->slug];

        // Quando lo slug coincide con la sezione l'URL canonico è la sezione
        // e basta: la regola generale produceva /summer-camp/summer-camp, che
        // rispondeva 200 ed era la stessa pagina a due indirizzi.
        if ($page->slug === $section) {
            return request()->routeIs($routePrefix.$section) || ! Route::has($routePrefix.$section)
                ? null
                : redirect()->route($routePrefix.$section, [], 301);
        }

        if (! request()->routeIs('*pages.show')) {
            return null;
        }

        return redirect()->route($routePrefix.$section.'.page', ['slug' => $page->slug], 301);
    }

    /**
     * Carica dati aggiuntivi in base al template.
     * Ogni template specializzato riceve le props che il componente Vue si aspetta.
     */
    private function getTemplateData(string $template): array
    {
        return match ($template) {
            'Public/Societa/Organigramma' => $this->getSocietaData(),
            'Public/Comunicazione' => $this->getComunicazioneData(),
            'Public/Roster' => $this->getRosterData(),
            'Public/Sponsor' => $this->getSponsorData(),
            default => [],
        };
    }

    /**
     * Le prossime gare in casa, per la tendina del modulo accrediti.
     *
     * La gara si scriveva a mano e arrivavano richieste per partite che non
     * esistono o scritte in dieci modi diversi. Sono le prossime due in
     * calendario in cui la squadra di casa e' una squadra della societa': le
     * trasferte non si accreditano qui.
     *
     * Costruire questo elenco faceva morire php-fpm di segmentation fault
     * (`child ... exited on signal 11`), e con lui la pagina, che rispondeva
     * 503 senza lasciare traccia nei log applicativi. Il risultato sta in
     * cache mezz'ora: finché la chiave c'era la pagina si disegnava, e alla
     * sua scadenza — o dopo il `cache:clear` di ogni deploy — la prima
     * richiesta che provava a ricostruirla mandava giù le due pagine per
     * tutti. Lo stesso codice eseguito da CLI nello stesso container non è mai
     * crollato: non sono i dati né la memoria (picco 18 MB), e nemmeno
     * opcache o il JIT. L'unico costrutto fuori dall'ordinario era
     * `Carbon::translatedFormat()`, che qui è l'unica occorrenza di tutto il
     * frontend pubblico; la data ora si compone con le traduzioni del
     * progetto (`site.months`).
     *
     * Le due pagine hanno retto lo scadere della cache e il `cache:clear` del
     * deploy del 21/09/2026: le righe di diagnostica che accompagnavano ogni
     * passo non servono più.
     *
     * @return array{upcomingHomeGames: list<array{value: string, label: string}>}
     */
    private function getComunicazioneData(): array
    {
        return [
            'upcomingHomeGames' => Cache::remember('public:accrediti:gare:'.app()->getLocale(), now()->addMinutes(30), function () {
                return Game::with(['homeTeam', 'awayTeam'])
                    ->where('status', GameStatus::Scheduled)
                    ->where('match_date', '>=', now())
                    ->whereHas('homeTeam', fn ($team) => $team->where('is_internal', true))
                    ->orderBy('match_date')
                    ->take(2)
                    ->get()
                    ->map(function (Game $gara) {
                        // Le due squadre e la data ci sono per costruzione: la
                        // query filtra su una squadra di casa interna e su una
                        // data futura.
                        $sfida = $gara->homeTeam->name.' — '.$gara->awayTeam->name;

                        return [
                            'value' => $sfida,
                            'label' => $sfida.' · '.self::dataEstesa($gara->match_date),
                        ];
                    })
                    ->values()
                    ->all();
            }),
        ];
    }

    /**
     * La data per esteso ("4 ottobre 2026"), nella lingua della richiesta.
     *
     * Non usa `translatedFormat()` né `isoFormat()`: passano entrambi dal
     * traduttore di Carbon, ed è lì che il processo moriva.
     */
    private static function dataEstesa(CarbonInterface $data): string
    {
        $chiave = 'site.months.'.$data->format('n');
        $mese = __($chiave);

        // `__()` restituisce la chiave quando la traduzione manca: in quel caso
        // meglio il nome inglese di Carbon che "site.months.3" in pagina.
        if (! is_string($mese) || $mese === $chiave) {
            $mese = $data->format('F');
        }

        return $data->format('j').' '.$mese.' '.$data->format('Y');
    }

    private function getSocietaData(): array
    {
        $locale = app()->getLocale();

        return [
            'dirigenza' => Cache::remember("public:organigramma:page:{$locale}", now()->addMinutes(30), function () {
                return StaffMember::with('media')
                    ->where('type', StaffType::Dirigenza)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->full_name,
                        'role' => $p->role,
                        'photo_url' => $p->getFirstMediaUrl('staff', 'card') ?: $p->getFirstMediaUrl('staff'),
                    ])
                    ->toArray();
            }),
        ];
    }

    private function getRosterData(): array
    {
        $locale = app()->getLocale();

        return Cache::remember("public:roster_page:{$locale}", now()->addMinutes(10), function () {
            $currentSeason = Season::current()->latest('id')->first() ?? Season::latest('id')->first();
            $team = $currentSeason ? Team::where('category', 'A1')->first() : null;

            return [
                'players' => $team ? $this->atleteInRosa($team, $currentSeason) : [],
                'seasonName' => $currentSeason->name ?? __('Stagione corrente'),
            ];
        });
    }

    /**
     * Le atlete della rosa, ordinate per numero di maglia (chi non ce l'ha in
     * fondo), gia' pronte per il frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    private function atleteInRosa(Team $team, Season $season): array
    {
        return Roster::with(['player', 'media'])
            ->whereHas('player')
            ->where('team_id', $team->id)
            ->where('season_id', $season->id)
            ->orderByRaw('jersey_number IS NULL, jersey_number')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->player->id ?? $r->id,
                'first_name' => $r->player->first_name ?? '',
                'last_name' => $r->player->last_name ?? '',
                'number' => $r->jersey_number,
                'role' => $r->role?->value,
                'photo_url' => $r->getFirstMediaUrl('rosters_official', 'card') ?: ($r->player?->getFirstMediaUrl('players', 'card') ?: $r->player?->getFirstMediaUrl('players') ?: null),
            ])
            ->toArray();
    }

    private function getSponsorData(): array
    {
        return [
            'tiers' => app(SponsorDirectory::class)->tiers(),
        ];
    }
}
