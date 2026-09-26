<?php

namespace Tests\Feature;

use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Mail\ConfermaIscrizioneNewsletter;
use App\Models\NewsletterSubscriber;
use App\Services\ActiveCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Linee guida del Garante del 17/04/2026 sui pixel nelle email: dal link in
 * fondo a ogni newsletter si revoca il solo tracciamento, continuando a
 * ricevere, oppure tutto. La revoca arriva ad ActiveCampaign come tag, e il
 * link alle preferenze come campo personalizzato del contatto.
 */
class NewsletterPreferenzeTest extends TestCase
{
    use RefreshDatabase;

    private function iscritto(array $attributi = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::create(array_merge([
            'email' => 'tifoso@example.com',
            'source' => 'website',
            'subscribed_at' => now(),
            'confermato_il' => now(),
            'synced_to_ac' => true,
            'ac_contact_id' => 4242,
        ], $attributi));
    }

    public function test_senza_firma_la_pagina_non_si_apre(): void
    {
        $iscritto = $this->iscritto();

        $this->get(route('newsletter.preferenze.show', ['subscriber' => $iscritto->id]))->assertForbidden();
    }

    public function test_la_pagina_offre_le_due_revoche(): void
    {
        $iscritto = $this->iscritto();

        $this->get($iscritto->preferenzeUrl())
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->component('Public/NewsletterPreferenze')
                ->where('tracciamentoAttivo', true)
                ->where('iscritto', true)
                ->has('senzaTracciamentoUrl')
                ->has('disiscrivitiUrl'));
    }

    public function test_revocare_il_tracciamento_lascia_l_iscrizione_e_avvisa_activecampaign(): void
    {
        Queue::fake();
        $iscritto = $this->iscritto();

        $url = route('newsletter.preferenze.senza-tracciamento', ['subscriber' => $iscritto->id]);
        $this->post($url)->assertForbidden();

        $firmato = URL::signedRoute('newsletter.preferenze.senza-tracciamento', ['subscriber' => $iscritto->id]);
        $this->post($firmato)->assertRedirect($iscritto->preferenzeUrl());

        $iscritto->refresh();
        $this->assertNotNull($iscritto->tracciamento_revocato_il);
        $this->assertTrue($iscritto->isSubscribed());
        Queue::assertPushed(SyncNewsletterToActiveCampaign::class);

        // Idempotente: la seconda revoca non cambia la data.
        $prima = $iscritto->tracciamento_revocato_il;
        $this->travel(1)->hours();
        $this->assertFalse($iscritto->revocaTracciamento());
        $this->assertTrue($prima->equalTo($iscritto->fresh()->tracciamento_revocato_il));
    }

    public function test_il_sync_manda_il_link_alle_preferenze_e_il_tag_senza_tracciamento(): void
    {
        config([
            'services.activecampaign.url' => 'https://savino.api-us1.com',
            'services.activecampaign.key' => 'chiave-di-prova',
            'services.activecampaign.list_id' => 3,
            'services.activecampaign.campo_preferenze' => 7,
        ]);

        Http::fake([
            '*/api/3/contact/sync' => Http::response(['contact' => ['id' => 4242]]),
            '*/api/3/contactLists' => Http::response([], 201),
            '*/api/3/tags*' => Http::response(['tags' => [['id' => 55, 'tag' => 'senza-tracciamento']]]),
            '*/api/3/contactTags' => Http::response([], 201),
        ]);

        $iscritto = $this->iscritto(['tracciamento_revocato_il' => now()]);

        (new SyncNewsletterToActiveCampaign($iscritto))->handle(new ActiveCampaignService);

        Http::assertSent(fn ($richiesta) => str_ends_with($richiesta->url(), '/api/3/contact/sync')
            && $richiesta['contact']['fieldValues'][0]['field'] === '7'
            && str_contains($richiesta['contact']['fieldValues'][0]['value'], '/newsletter/preferenze/'.$iscritto->id));
        Http::assertSent(fn ($richiesta) => str_ends_with($richiesta->url(), '/api/3/contactTags')
            && $richiesta['contactTag'] === ['contact' => 4242, 'tag' => 55]);
    }

    public function test_senza_revoca_il_sync_non_mette_il_tag(): void
    {
        config([
            'services.activecampaign.url' => 'https://savino.api-us1.com',
            'services.activecampaign.key' => 'chiave-di-prova',
            'services.activecampaign.list_id' => 3,
        ]);

        Http::fake([
            '*/api/3/contact/sync' => Http::response(['contact' => ['id' => 4242]]),
            '*/api/3/contactLists' => Http::response([], 201),
        ]);

        (new SyncNewsletterToActiveCampaign($this->iscritto()))->handle(new ActiveCampaignService);

        Http::assertNotSent(fn ($richiesta) => str_contains($richiesta->url(), 'contactTags'));
        Http::assertSent(fn ($richiesta) => str_ends_with($richiesta->url(), '/api/3/contact/sync')
            && ! isset($richiesta['contact']['fieldValues']));
    }

    public function test_il_consenso_dice_del_tracciamento_nel_modulo_e_nell_email_di_conferma(): void
    {
        $it = json_decode((string) file_get_contents(resource_path('js/i18n/it.json')), true);
        $en = json_decode((string) file_get_contents(resource_path('js/i18n/en.json')), true);

        $this->assertStringContainsString('aperture e clic', $it['newsletter']['privacy_consent']);
        $this->assertStringContainsString('opens and clicks', $en['newsletter']['privacy_consent']);

        $html = (new ConfermaIscrizioneNewsletter($this->iscritto(['confermato_il' => null]), 'it'))->render();
        $this->assertStringContainsString('pixel e link tracciati', $html);
    }
}
