<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_contact_page_returns_200(): void
    {
        $response = $this->get(route('contatti'));
        $response->assertStatus(200);
    }

    public function test_contact_form_submission_with_valid_data(): void
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
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $response = $this->post(route('contatti.submit'), [
            'honeypot' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
    }

    public function test_contact_form_validates_email_format(): void
    {
        $response = $this->post(route('contatti.submit'), [
            'name' => 'Marco',
            'email' => 'not-an-email',
            'message' => 'Test message',
            'honeypot' => '',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_honeypot_traps_bots(): void
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
