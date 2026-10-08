<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function contact_page_returns_200(): void
    {
        $response = $this->get(route('contatti'));
        $response->assertStatus(200);
    }

    #[Test]
    public function contact_form_submission_with_valid_data(): void
    {
        Mail::fake();

        $response = $this->post(route('contatti.submit'), [
            'name' => 'Marco Rossi',
            'email' => 'marco@example.com',
            'message' => 'Vorrei informazioni sulla prossima partita.',
            'honeypot' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verifica la persistenza nel database
        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Marco Rossi',
            'email' => 'marco@example.com',
            'message' => 'Vorrei informazioni sulla prossima partita.',
            'status' => 'unread',
        ]);
    }

    #[Test]
    public function la_notifica_va_al_recapito_dei_contatti_non_al_mittente_di_sistema(): void
    {
        config(['mail.default' => 'array', 'mail.from.address' => 'noreply@example.test']);
        SiteSetting::updateOrCreate(['key' => 'email'], ['group' => 'contact', 'value' => 'info@example.test', 'type' => 'text']);
        Cache::flush();

        $this->post(route('contatti.submit'), [
            'name' => 'Marco Rossi',
            'email' => 'marco@example.com',
            'message' => 'Vorrei informazioni sulla prossima partita.',
            'honeypot' => '',
        ])->assertRedirect();

        $inviate = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $inviate);
        $this->assertSame('info@example.test', $inviate[0]->getOriginalMessage()->getTo()[0]->getAddress());
    }

    #[Test]
    public function contact_form_validates_required_fields(): void
    {
        $response = $this->post(route('contatti.submit'), [
            'honeypot' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
    }

    #[Test]
    public function contact_form_validates_email_format(): void
    {
        $response = $this->post(route('contatti.submit'), [
            'name' => 'Marco',
            'email' => 'not-an-email',
            'message' => 'Test message',
            'honeypot' => '',
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function honeypot_traps_bots(): void
    {
        Mail::fake();

        $response = $this->post(route('contatti.submit'), [
            'name' => 'Bot',
            'email' => 'bot@spam.com',
            'message' => 'Buy cheap stuff',
            'honeypot' => 'bot',
        ]);

        // La validazione max:0 sul honeypot blocca la request prima del controller
        $response->assertSessionHasErrors('honeypot');

        Mail::assertNothingSent();
    }
}
