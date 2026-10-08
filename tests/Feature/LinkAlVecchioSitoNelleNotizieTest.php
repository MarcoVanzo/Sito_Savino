<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I link assoluti alle pagine del vecchio sito rimasti nelle notizie
 * importate da WordPress: quelli ai media li ha già riscritti
 * `news:importa-i-media-dal-vecchio-sito`.
 */
class LinkAlVecchioSitoNelleNotizieTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRAZIONE = '2026_10_08_180000_link_alle_pagine_del_vecchio_sito_nelle_notizie';

    /** Il testo della notizia 9, letto in produzione. */
    private const BIGLIETTERIA = '<p><a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a><br />'
        ."\n".'https://savinodelbenevolley.it/biglietteria/ </p>';

    /** Il testo della notizia 341. */
    private const TALENT_DAY = '<p>compila il form al seguente link!<br />'
        ."\n".'https://savinodelbenevolley.it/talentday/</p>';

    /**
     * Il testo della notizia 439. Fra "dedicata" e l'indirizzo c'è lo spazio
     * unificatore lasciato dall'editor di WordPress, non uno spazio normale.
     */
    private const JAM_CAMP = '<div>Per maggiori informazioni visita la pagina dedicata'
        ."\u{A0}".'https://savinodelbenevolley.it/Jam-Camp o scrivi a '
        .'<a href="mailto:volley@jamcamp.it" target="_blank" rel="noopener">volley@jamcamp.it</a></div>';

    private function migrazione(): mixed
    {
        return require database_path('migrations/'.self::MIGRAZIONE.'.php');
    }

    /**
     * @param  array<string, string>  $content
     */
    private function notizia(array $content, ?string $excerpt = null): Post
    {
        return Post::factory()->create([
            'content' => $content,
            'excerpt' => $excerpt === null ? ['it' => ''] : ['it' => $excerpt],
        ]);
    }

    private function contenuto(Post $notizia, string $lingua = 'it'): string
    {
        return json_decode((string) DB::table('posts')->where('id', $notizia->id)->value('content'), true)[$lingua];
    }

    #[Test]
    public function il_link_alla_biglietteria_diventa_la_rotta_della_sezione(): void
    {
        $notizia = $this->notizia(['it' => self::BIGLIETTERIA]);

        $this->migrazione()->up();

        $this->assertSame(
            '<p><a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a><br />'
                ."\n".'<a href="/ticketing/biglietteria">Biglietteria</a></p>',
            $this->contenuto($notizia),
        );
    }

    #[Test]
    public function il_link_al_talent_day_usa_lo_slug_nuovo(): void
    {
        // Sul vecchio sito la pagina era `/talentday/`, qui è `talent-day`
        // sotto la sezione `/youth`.
        $notizia = $this->notizia(['it' => self::TALENT_DAY]);

        $this->migrazione()->up();

        $this->assertSame(
            '<p>compila il form al seguente link!<br />'."\n".'<a href="/youth/talent-day">Talent Day</a></p>',
            $this->contenuto($notizia),
        );
    }

    #[Test]
    public function la_pagina_del_jam_camp_non_esiste_e_l_indirizzo_si_toglie(): void
    {
        $notizia = $this->notizia(['it' => self::JAM_CAMP]);

        $this->migrazione()->up();

        $testo = $this->contenuto($notizia);

        $this->assertStringNotContainsString('savinodelbenevolley.it/Jam-Camp', $testo);
        // Il modulo e l'indirizzo del camp restano: sono il vero recapito.
        $this->assertStringContainsString('Per maggiori informazioni scrivi a ', $testo);
        $this->assertStringContainsString('mailto:volley@jamcamp.it', $testo);
    }

    #[Test]
    public function la_frase_del_jam_camp_si_riconosce_anche_con_lo_spazio_normale(): void
    {
        $notizia = $this->notizia(['it' => str_replace("\u{A0}", ' ', self::JAM_CAMP)]);

        $this->migrazione()->up();

        $this->assertStringNotContainsString('savinodelbenevolley.it/Jam-Camp', $this->contenuto($notizia));
    }

    #[Test]
    public function in_inglese_il_percorso_porta_il_prefisso_della_lingua(): void
    {
        $notizia = $this->notizia(['it' => self::TALENT_DAY, 'en' => self::TALENT_DAY]);

        $this->migrazione()->up();

        $this->assertStringContainsString('href="/youth/talent-day"', $this->contenuto($notizia));
        $this->assertStringContainsString('href="/en/youth/talent-day"', $this->contenuto($notizia, 'en'));
    }

    #[Test]
    public function gli_indirizzi_email_non_sono_link_e_restano(): void
    {
        // Quasi tutte le occorrenze del dominio fuori dai media sono email.
        $testo = '<p>scrivi a <a href="mailto:ticketing@savinodelbenevolley.it">ticketing@savinodelbenevolley.it</a> '
            .'oppure a press@savinodelbenevolley.it</p>';
        $notizia = $this->notizia(['it' => $testo], 'Scrivi a info@savinodelbenevolley.it.');

        $this->migrazione()->up();

        $this->assertSame($testo, $this->contenuto($notizia));
        $this->assertSame(
            'Scrivi a info@savinodelbenevolley.it.',
            json_decode((string) DB::table('posts')->where('id', $notizia->id)->value('excerpt'), true)['it'],
        );
    }

    #[Test]
    public function il_sottodominio_della_web_app_resta_com_e(): void
    {
        // app.savinodelbenevolley.it ha un DNS suo: non è una pagina di questo sito.
        $testo = '<p>La web-app <a href="https://app.savinodelbenevolley.it/">app.savinodelbenevolley.it</a> è accessibile.</p>';
        $notizia = $this->notizia(['it' => $testo]);

        $this->migrazione()->up();

        $this->assertSame($testo, $this->contenuto($notizia));
    }

    #[Test]
    public function i_media_gia_riscritti_dal_comando_non_si_toccano(): void
    {
        $testo = '<p><img src="/storage/news/foto.jpg" /> e '
            .'<a href="https://savinodelbenevolley.it/wp-content/uploads/2023/01/locandina.pdf">la locandina</a></p>';
        $notizia = $this->notizia(['it' => $testo]);

        $this->migrazione()->up();

        $this->assertSame($testo, $this->contenuto($notizia));
    }

    #[Test]
    public function una_frase_gia_riscritta_dalla_redazione_non_si_tocca(): void
    {
        // La guardia è il contesto in cui il link è stato letto in produzione.
        $testo = '<p>Tutte le informazioni sulla <a href="/ticketing/biglietteria">biglietteria</a>.</p>';
        $notizia = $this->notizia(['it' => $testo]);

        $this->migrazione()->up();

        $this->assertSame($testo, $this->contenuto($notizia));
    }

    #[Test]
    public function rilanciarla_non_cambia_nulla(): void
    {
        $notizia = $this->notizia(['it' => self::BIGLIETTERIA.self::TALENT_DAY.self::JAM_CAMP]);

        $this->migrazione()->up();
        $dopoLaPrima = $this->contenuto($notizia);
        $aggiornataIl = DB::table('posts')->where('id', $notizia->id)->value('updated_at');

        $this->migrazione()->up();

        $this->assertSame($dopoLaPrima, $this->contenuto($notizia));
        $this->assertSame($aggiornataIl, DB::table('posts')->where('id', $notizia->id)->value('updated_at'));
    }

    #[Test]
    public function nessuna_notizia_cita_piu_una_pagina_del_vecchio_sito(): void
    {
        $this->notizia(['it' => self::BIGLIETTERIA]);
        $this->notizia(['it' => self::TALENT_DAY]);
        $this->notizia(['it' => self::JAM_CAMP]);

        $this->migrazione()->up();

        foreach (Post::all() as $notizia) {
            $testo = $this->contenuto($notizia);

            // Restano solo le email e i media: nessun `//savinodelbenevolley.it/pagina`.
            $this->assertDoesNotMatchRegularExpression(
                '#https?://(?:[a-z0-9.-]*\.)?savinodelbenevolley\.it/(?!wp-content/uploads/)#i',
                $testo,
            );
        }
    }
}
