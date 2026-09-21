<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le notizie importate da WordPress citano ancora immagini e PDF ospitati sul
 * vecchio sito: funzionano finche' il dominio non migra, poi si spengono.
 *
 * Qui si verifica che il comando li porti sul disco del sito e riscriva i
 * link, senza toccare quello che non e' suo.
 */
class ImportaIMediaDelleNotizieTest extends TestCase
{
    use RefreshDatabase;

    /** Un PNG vero di un pixel: il comando controlla che il file sia un'immagine. */
    private function pixel(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    private function fingiIlVecchioSito(): void
    {
        Http::fake([
            'savinodelbenevolley.it/wp-content/uploads/*.pdf' => Http::response('%PDF-1.4 finto documento'),
            'savinodelbenevolley.it/wp-content/uploads/*.odt' => Http::response("PK\x03\x04finto-archivio"),
            'savinodelbenevolley.it/wp-content/uploads/*' => Http::response($this->pixel(), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    private function notiziaCon(string $html, ?string $excerpt = null): Post
    {
        return Post::factory()->create(['content' => $html, 'excerpt' => $excerpt ?? 'Sommario']);
    }

    #[Test]
    public function copia_l_immagine_e_riscrive_il_link(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $notizia = $this->notiziaCon(
            '<p><img src="https://savinodelbenevolley.it/wp-content/uploads/2022/10/Locandina-211x300.jpg" alt="" /></p>'
        );

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        Storage::assertExists('news/2022/10/Locandina-211x300.jpg');

        $contenuto = $notizia->fresh()->getTranslation('content', 'it');
        $this->assertStringNotContainsString('savinodelbenevolley.it/wp-content', $contenuto);
        $this->assertStringContainsString('news/2022/10/Locandina-211x300.jpg', $contenuto);
    }

    /**
     * Il link incollato nudo: WordPress ripete l'indirizzo come etichetta, e
     * quella restava a nominare il vecchio dominio anche dopo la copia.
     */
    #[Test]
    public function riscrive_anche_l_indirizzo_scritto_nel_testo(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $notizia = $this->notiziaCon(
            '<p>Il documento si scarica qui:<br />'
            .'<a href="https://savinodelbenevolley.it/wp-content/uploads/2026/06/bilancio.pdf">'
            .'https://savinodelbenevolley.it/wp-content/uploads/2026/06/bilancio.pdf</a>.</p>'
        );

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        $contenuto = $notizia->fresh()->getTranslation('content', 'it');

        $this->assertStringNotContainsString('savinodelbenevolley.it/wp-content', $contenuto);
        $this->assertSame(2, substr_count($contenuto, 'news/2026/06/bilancio.pdf'), 'href ed etichetta devono puntare allo stesso file');
        // Il punto di fine frase non fa parte del nome del file.
        $this->assertStringEndsWith('</a>.</p>', $contenuto);
    }

    #[Test]
    public function copia_anche_i_pdf_collegati(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $notizia = $this->notiziaCon(
            '<p><a href="https://savinodelbenevolley.it/wp-content/uploads/2022/09/Calendario_2022-23.pdf">Calendario</a></p>'
        );

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        Storage::assertExists('news/2022/09/Calendario_2022-23.pdf');
        $this->assertStringContainsString(
            'news/2022/09/Calendario_2022-23.pdf',
            $notizia->fresh()->getTranslation('content', 'it')
        );
    }

    /**
     * I ritagli di `srcset` sono un centinaio di indirizzi che il frontend
     * scarta comunque: si tolgono invece di copiarli, altrimenti resterebbero
     * in archivio a puntare a un dominio che non risponde piu'.
     */
    #[Test]
    public function toglie_i_ritagli_di_wordpress_invece_di_copiarli(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $notizia = $this->notiziaCon(
            '<img src="https://savinodelbenevolley.it/wp-content/uploads/2023/07/FASCE-300x156.jpg" '
            .'srcset="https://savinodelbenevolley.it/wp-content/uploads/2023/07/FASCE-300x156.jpg 300w, '
            .'https://savinodelbenevolley.it/wp-content/uploads/2023/07/FASCE-768x399.jpg 768w" '
            .'sizes="(max-width: 300px) 100vw, 300px" width="300" height="156" />'
        );

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        $contenuto = $notizia->fresh()->getTranslation('content', 'it');

        $this->assertStringNotContainsString('srcset', $contenuto);
        $this->assertStringNotContainsString('sizes=', $contenuto);
        $this->assertStringContainsString('width="300"', $contenuto);
        Storage::assertExists('news/2023/07/FASCE-300x156.jpg');
        Storage::assertMissing('news/2023/07/FASCE-768x399.jpg');
    }

    #[Test]
    public function rilanciarlo_non_riscarica_niente(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $this->notiziaCon('<img src="https://savinodelbenevolley.it/wp-content/uploads/2022/10/Locandina.jpg" />');

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();
        $richiesteDelPrimoGiro = count(Http::recorded());

        $this->artisan('news:importa-i-media-dal-vecchio-sito')
            ->expectsOutputToContain('Nessuna notizia prende media dal vecchio sito.')
            ->assertSuccessful();

        $this->assertCount($richiesteDelPrimoGiro, Http::recorded());
    }

    #[Test]
    public function la_prova_non_scarica_e_non_scrive(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $originale = '<img src="https://savinodelbenevolley.it/wp-content/uploads/2022/10/Locandina.jpg" />';
        $notizia = $this->notiziaCon($originale);

        $this->artisan('news:importa-i-media-dal-vecchio-sito --prova')
            ->expectsOutputToContain('1 file da copiare')
            ->assertSuccessful();

        Storage::assertMissing('news/2022/10/Locandina.jpg');
        $this->assertSame($originale, $notizia->fresh()->getTranslation('content', 'it'));
        Http::assertNothingSent();
    }

    #[Test]
    public function non_tocca_gli_indirizzi_di_altri_siti(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $originale = '<p><a href="https://www.legavolleyfemminile.it/calendario/">Calendario</a> '
            .'<img src="https://esempio.it/wp-content/uploads/2022/10/altro.jpg" /></p>';
        $notizia = $this->notiziaCon($originale);

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame($originale, $notizia->fresh()->getTranslation('content', 'it'));
        Http::assertNothingSent();
    }

    /**
     * Il vecchio sito serve con 200 anche la pagina "non trovato": salvarla
     * come se fosse la locandina metterebbe in archivio un link che sembra
     * buono e non lo e'.
     */
    #[Test]
    public function una_pagina_di_errore_non_diventa_un_file(): void
    {
        Storage::fake();
        Http::fake([
            'savinodelbenevolley.it/*' => Http::response('<html><body>Pagina non trovata</body></html>'),
        ]);

        $originale = '<img src="https://savinodelbenevolley.it/wp-content/uploads/2022/10/Sparita.jpg" />';
        $notizia = $this->notiziaCon($originale);

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertFailed();

        Storage::assertMissing('news/2022/10/Sparita.jpg');
        $this->assertSame($originale, $notizia->fresh()->getTranslation('content', 'it'));
    }

    /**
     * `Tabella-costi.jpg` esiste sia sotto 2023/07 sia sotto 2023/08, e sono
     * due locandine diverse: senza la cartella del mese la seconda
     * sovrascriverebbe la prima.
     */
    #[Test]
    public function due_file_omonimi_di_mesi_diversi_non_si_sovrascrivono(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $this->notiziaCon('<img src="https://savinodelbenevolley.it/wp-content/uploads/2023/07/Tabella-costi.jpg" />');
        $this->notiziaCon('<img src="https://savinodelbenevolley.it/wp-content/uploads/2023/08/Tabella-costi.jpg" />');

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        Storage::assertExists('news/2023/07/Tabella-costi.jpg');
        Storage::assertExists('news/2023/08/Tabella-costi.jpg');
    }

    /**
     * `content` e' tradotto: lavorando sulla sola lingua attiva l'inglese
     * sarebbe rimasto appeso al vecchio dominio.
     */
    #[Test]
    public function riscrive_tutte_le_lingue(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $notizia = Post::factory()->create([
            'content' => [
                'it' => '<img src="https://savinodelbenevolley.it/wp-content/uploads/2022/10/Locandina.jpg" />',
                'en' => '<img src="https://savinodelbenevolley.it/wp-content/uploads/2022/10/Locandina.jpg" />',
            ],
        ]);

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        foreach (['it', 'en'] as $lingua) {
            $this->assertStringNotContainsString(
                'savinodelbenevolley.it/wp-content',
                $notizia->fresh()->getTranslation('content', $lingua)
            );
        }
    }

    /**
     * Le cartelle stampa sono comunicati in ODT: senza di loro la notizia
     * resterebbe con il solo titolo e un link a un dominio spento.
     */
    #[Test]
    public function copia_i_comunicati_delle_cartelle_stampa(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $notizia = $this->notiziaCon(
            '<p><a href="https://savinodelbenevolley.it/wp-content/uploads/2025/10/Coppa-Italia-Quarti.odt">Comunicato</a></p>'
        );

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        Storage::assertExists('news/2025/10/Coppa-Italia-Quarti.odt');
        $this->assertStringContainsString(
            'news/2025/10/Coppa-Italia-Quarti.odt',
            $notizia->fresh()->getTranslation('content', 'it')
        );
    }

    /**
     * Il nome del file passa per l'indirizzo: gli spazi e gli accenti che
     * WordPress accetta vanno ridotti, o il link si rompe di nuovo.
     */
    #[Test]
    public function ripulisce_i_nomi_che_un_indirizzo_non_regge(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $notizia = $this->notiziaCon(
            '<p><a href="https://savinodelbenevolley.it/wp-content/uploads/2025/10/CEV-Pool-A-1%C2%B0-turno.odt">Comunicato</a></p>'
        );

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        Storage::assertExists('news/2025/10/CEV-Pool-A-1-turno.odt');
        $this->assertStringNotContainsString('%C2%B0', $notizia->fresh()->getTranslation('content', 'it'));
    }

    /**
     * Novanta file si scaricano in qualche minuto: se una notizia si mette di
     * traverso, le altre devono comunque arrivare in fondo, o il giro dopo
     * ricomincerebbe da capo.
     */
    #[Test]
    public function una_notizia_in_errore_non_ferma_le_altre(): void
    {
        Storage::fake();
        Http::fake([
            'savinodelbenevolley.it/wp-content/uploads/2022/10/Rotta.jpg' => Http::response('', 500),
            'savinodelbenevolley.it/wp-content/uploads/*' => Http::response($this->pixel(), 200),
        ]);

        $rotta = $this->notiziaCon('<img src="https://savinodelbenevolley.it/wp-content/uploads/2022/10/Rotta.jpg" />');
        $buona = $this->notiziaCon('<img src="https://savinodelbenevolley.it/wp-content/uploads/2022/10/Buona.jpg" />');

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertFailed();

        Storage::assertExists('news/2022/10/Buona.jpg');
        $this->assertStringContainsString('news/2022/10/Buona.jpg', $buona->fresh()->getTranslation('content', 'it'));
        $this->assertStringContainsString('savinodelbenevolley.it', $rotta->fresh()->getTranslation('content', 'it'));
    }

    #[Test]
    public function riscrive_anche_il_sommario(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito();

        $notizia = $this->notiziaCon(
            '<p>Senza media.</p>',
            '<a href="https://savinodelbenevolley.it/wp-content/uploads/2024/07/Calendario.pdf">Scarica</a>'
        );

        $this->artisan('news:importa-i-media-dal-vecchio-sito')->assertSuccessful();

        Storage::assertExists('news/2024/07/Calendario.pdf');
        $this->assertStringContainsString(
            'news/2024/07/Calendario.pdf',
            $notizia->fresh()->getTranslation('excerpt', 'it')
        );
    }
}
