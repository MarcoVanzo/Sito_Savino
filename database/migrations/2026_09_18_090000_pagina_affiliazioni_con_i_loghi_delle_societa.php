<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Progetto Affiliazioni: la pagina passa dal template generico a uno suo, che
 * mostra i loghi delle societa' divisi nei tre livelli del sito precedente
 * (Main Partner, Partner Ufficiale, Societa' Affiliate), ognuno con il
 * collegamento al proprio sito.
 *
 * Il racconto del progetto resta dov'e', nell'editor della pagina: il template
 * nuovo lo pubblica sopra l'elenco. L'elenco nasce vuoto e si riempie dal
 * pannello, oppure in un colpo solo con
 * `php artisan affiliazioni:importa-dal-vecchio-sito`.
 *
 * Del racconto se ne vanno solo le due sezioni che elencavano i partner a
 * testo ("Main Partner: Fusion Team Volley, Vola Valley." e "Partner
 * Ufficiali: ..."): sono esattamente le societa' che l'elenco pubblica ora
 * con il logo e il collegamento al sito, e lasciarle le farebbe comparire due
 * volte nella stessa pagina. Il resto del testo non si tocca.
 */
return new class extends Migration
{
    /**
     * I titoli delle sezioni che elencavano i partner a testo, nelle due
     * lingue. Si toglie il titolo e il blocco che lo segue (il paragrafo con i
     * nomi), niente altro.
     *
     * @var list<string>
     */
    private const SEZIONI_SOSTITUITE_DALL_ELENCO = [
        'Main Partner',
        'Main Partners',
        'Partner Ufficiale',
        'Partner Ufficiali',
        'Official Partner',
        'Official Partners',
    ];

    private const TESTI = [
        'it' => [
            'hero_label' => 'SDB Youth',
            'hero_description' => 'Le società che crescono insieme alla Savino Del Bene Volley.',
            'clubs_heading' => 'Le società del progetto',
        ],
        'en' => [
            'hero_label' => 'SDB Youth',
            'hero_description' => 'The clubs growing alongside Savino Del Bene Volley.',
            'clubs_heading' => 'The clubs in the project',
        ],
    ];

    public function up(): void
    {
        $pagina = DB::table('pages')->where('slug', 'affiliazioni')->first(['id', 'template', 'content', 'content_data']);

        if (! $pagina) {
            return;
        }

        $contenuti = json_decode((string) $pagina->content_data, true);
        $contenuti = is_array($contenuti) ? $contenuti : [];

        foreach (self::TESTI as $lingua => $testi) {
            $valori = is_array($contenuti[$lingua] ?? null) ? $contenuti[$lingua] : [];

            foreach ($testi as $chiave => $testo) {
                // Quello che la redazione ha gia' scritto vince.
                $valori[$chiave] = $valori[$chiave] ?? $testo;
            }

            $valori['affiliates'] = is_array($valori['affiliates'] ?? null) ? $valori['affiliates'] : [];
            $contenuti[$lingua] = $valori;
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            'template' => 'Public/Affiliazioni',
            'content' => $this->raccontoSenzaGliElenchi($pagina->content),
            'content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        foreach (array_keys($contenuti) as $lingua) {
            Cache::forget('public:page:affiliazioni:'.$lingua);
        }

        CachePublicResponse::flush();
    }

    /**
     * Il template torna a quello generico e l'elenco delle societa'
     * semplicemente non viene piu' pubblicato. Le due sezioni tolte dal
     * racconto non si ricostruiscono: i nomi stanno nell'elenco, da cui la
     * redazione puo' riscriverle in un minuto.
     */
    public function down(): void
    {
        DB::table('pages')->where('slug', 'affiliazioni')->update([
            'template' => 'Public/ContentPage',
            'updated_at' => now(),
        ]);

        CachePublicResponse::flush();
    }

    /**
     * Toglie dal racconto — in ogni lingua — le sezioni che l'elenco delle
     * societa' ha reso doppie: il titolo e il blocco subito successivo.
     *
     * Il contenuto e' tradotto, quindi una mappa lingua => HTML; se in
     * archivio ci fosse testo semplice (capita sulle righe piu' vecchie) si
     * lavora su quello.
     */
    private function raccontoSenzaGliElenchi(mixed $contenuto): mixed
    {
        if (! is_string($contenuto) || trim($contenuto) === '') {
            return $contenuto;
        }

        $decodificato = json_decode($contenuto, true);

        if (! is_array($decodificato)) {
            return $this->senzaLeSezioni($contenuto);
        }

        foreach ($decodificato as $lingua => $testo) {
            if (is_string($testo)) {
                $decodificato[$lingua] = $this->senzaLeSezioni($testo);
            }
        }

        return json_encode($decodificato, JSON_UNESCAPED_UNICODE);
    }

    private function senzaLeSezioni(string $html): string
    {
        foreach (self::SEZIONI_SOSTITUITE_DALL_ELENCO as $titolo) {
            $html = preg_replace(
                '#<h[23][^>]*>\s*'.preg_quote($titolo, '#').'\s*</h[23]>\s*(<p\b.*?</p>|<ul\b.*?</ul>)?\s*#is',
                '',
                $html
            ) ?? $html;
        }

        return trim($html);
    }
};
