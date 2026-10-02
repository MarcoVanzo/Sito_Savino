<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use App\Models\VersioneTestiConsenso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La prova del consenso alla newsletter: quale testo è stato accettato
 * (EDPB 05/2020 §108), e che cosa resta dopo la disiscrizione — email e date
 * per ventiquattro mesi, nome e IP subito via.
 */
class ProvaDelConsensoNewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
    }

    public function test_la_richiesta_e_la_conferma_portano_l_impronta_del_testo_accettato(): void
    {
        $this->post(route('newsletter.subscribe'), [
            'email' => 'tifoso@example.com',
            'first_name' => 'Paola',
            'honeypot' => '',
            'privacy_accepted' => true,
        ])->assertRedirect();

        $iscritto = NewsletterSubscriber::where('email', 'tifoso@example.com')->firstOrFail();
        $versione = VersioneTestiConsenso::where('impronta', $iscritto->impronta_testi_modulo)->firstOrFail();

        $this->assertSame(VersioneTestiConsenso::TIPO_NEWSLETTER, $versione->tipo);
        $this->assertSame(hash('sha256', $versione->contenuto), $versione->impronta);

        $it = json_decode((string) file_get_contents(resource_path('js/i18n/it.json')), true);
        $en = json_decode((string) file_get_contents(resource_path('js/i18n/en.json')), true);
        $testi = $versione->testi();

        // La casella spuntata, nelle due lingue, e l'email con il pixel.
        $this->assertSame($it['newsletter']['privacy_consent'], $testi['lingue']['it']['modulo']['privacy_consent']);
        $this->assertSame($en['newsletter']['privacy_consent'], $testi['lingue']['en']['modulo']['privacy_consent']);
        $this->assertSame(__('emails.newsletter_conferma.tracciamento', [], 'it'), $testi['lingue']['it']['email_di_conferma']['tracciamento']);

        $this->assertNull($iscritto->impronta_testi_conferma);

        $conferma = URL::temporarySignedRoute('newsletter.conferma', now()->addHour(), ['subscriber' => $iscritto->id]);
        $this->post($conferma)->assertRedirect();

        $this->assertSame($iscritto->impronta_testi_modulo, $iscritto->fresh()->impronta_testi_conferma);
        $this->assertSame(1, VersioneTestiConsenso::count());
    }

    public function test_dal_link_nell_email_la_disiscrizione_cancella_nome_e_ip_e_tiene_la_prova(): void
    {
        $iscritto = NewsletterSubscriber::factory()->create([
            'first_name' => 'Paola',
            'last_name' => 'Rossi',
            'ip_address' => '203.0.113.7',
            'subscribed_at' => now()->subMonths(3),
            'confermato_il' => now()->subMonths(3),
        ]);

        $link = URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $iscritto->id]);
        $this->post($link)->assertRedirect();

        $dopo = $iscritto->fresh();
        $this->assertNull($dopo->first_name);
        $this->assertNull($dopo->last_name);
        $this->assertNull($dopo->ip_address);
        // La prova di iscrizione, conferma e revoca resta.
        $this->assertSame($iscritto->email, $dopo->email);
        $this->assertNotNull($dopo->subscribed_at);
        $this->assertNotNull($dopo->confermato_il);
        $this->assertNotNull($dopo->unsubscribed_at);
    }

    public function test_dal_pannello_la_disiscrizione_cancella_nome_e_ip(): void
    {
        // Le azioni "Disiscrivi" del pannello (una e in blocco) chiamano
        // unsubscribe('cms'): stesso metodo, stessa regola.
        $iscritto = NewsletterSubscriber::factory()->create([
            'first_name' => 'Paola',
            'ip_address' => '203.0.113.7',
        ]);

        $this->assertTrue($iscritto->unsubscribe('cms'));

        $this->assertNull($iscritto->fresh()->first_name);
        $this->assertNull($iscritto->fresh()->ip_address);
    }

    public function test_un_disiscritto_si_cancella_dopo_ventiquattro_mesi(): void
    {
        $vecchio = NewsletterSubscriber::factory()->create([
            'subscribed_at' => now()->subMonths(40),
            'confermato_il' => now()->subMonths(40),
            'unsubscribed_at' => now()->subMonths(25),
            'ac_contact_id' => 123,
        ]);
        $recente = NewsletterSubscriber::factory()->create([
            'subscribed_at' => now()->subMonths(30),
            'confermato_il' => now()->subMonths(30),
            'unsubscribed_at' => now()->subMonths(23),
        ]);
        // Disiscritto da tanto ma con una richiesta nuova in attesa di
        // conferma: non si toglie sotto i piedi di chi sta per cliccare.
        $inAttesa = NewsletterSubscriber::factory()->nonConfermato()->create([
            'subscribed_at' => now()->subDays(2),
            'unsubscribed_at' => now()->subMonths(26),
            'ac_contact_id' => 456,
        ]);
        $attivo = NewsletterSubscriber::factory()->create([
            'subscribed_at' => now()->subMonths(40),
            'confermato_il' => now()->subMonths(40),
        ]);

        $this->artisan('model:prune', ['--model' => [NewsletterSubscriber::class]])->assertSuccessful();

        $this->assertModelMissing($vecchio);
        $this->assertModelExists($recente);
        $this->assertModelExists($inAttesa);
        $this->assertModelExists($attivo);
    }

    public function test_la_migrazione_azzera_nome_e_ip_dei_gia_disiscritti(): void
    {
        $uscito = NewsletterSubscriber::factory()->create([
            'first_name' => 'Paola',
            'ip_address' => '203.0.113.7',
            'subscribed_at' => now()->subMonths(3),
            'unsubscribed_at' => now()->subMonth(),
        ]);
        // Rientrato con una richiesta nuova: nome e IP sono di quella.
        $rientrato = NewsletterSubscriber::factory()->nonConfermato()->create([
            'first_name' => 'Luca',
            'ip_address' => '203.0.113.8',
            'subscribed_at' => now()->subDay(),
            'unsubscribed_at' => now()->subMonth(),
        ]);
        $attivo = NewsletterSubscriber::factory()->create([
            'first_name' => 'Anna',
            'ip_address' => '203.0.113.9',
        ]);

        $migrazione = require database_path('migrations/2026_10_02_140200_newsletter_dimentica_nome_e_ip_dei_disiscritti.php');
        $migrazione->up();
        $migrazione->up();

        $this->assertNull($uscito->fresh()->first_name);
        $this->assertNull($uscito->fresh()->ip_address);
        $this->assertSame('Luca', $rientrato->fresh()->first_name);
        $this->assertSame('Anna', $attivo->fresh()->first_name);
        $this->assertSame('203.0.113.9', DB::table('newsletter_subscribers')->where('id', $attivo->id)->value('ip_address'));
    }
}
