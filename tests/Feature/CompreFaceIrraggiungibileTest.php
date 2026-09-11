<?php

namespace Tests\Feature;

use App\Filament\Actions\TrainAiFacesAction;
use App\Models\Player;
use App\Services\FacialRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Con CompreFace irraggiungibile "Addestra AI" dava un 500 a tutto schermo:
 * tre tentativi da dieci secondi l'uno superavano il tempo massimo della
 * richiesta e l'eccezione arrivava fino al pannello.
 */
class CompreFaceIrraggiungibileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.compreface.key' => 'chiave-di-prova', 'services.compreface.host' => 'http://10.0.0.1:8000']);
    }

    #[Test]
    public function un_server_che_non_risponde_non_si_ritenta(): void
    {
        $tentativi = 0;

        Http::fake(function () use (&$tentativi) {
            $tentativi++;

            throw new ConnectionException('cURL error 28: Connection timeout');
        });

        try {
            app(FacialRecognitionService::class)->createSubject(Player::factory()->create());
            $this->fail('Doveva arrivare la ConnectionException.');
        } catch (ConnectionException) {
            // atteso
        }

        $this->assertSame(1, $tentativi);
    }

    #[Test]
    public function l_addestramento_avvisa_invece_di_andare_in_errore(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timeout'));

        TrainAiFacesAction::execute(Player::factory()->create(), ['training_images' => []]);

        $notifiche = session('filament.notifications', []);

        $this->assertCount(1, $notifiche);
        $this->assertSame('Servizio AI non raggiungibile', $notifiche[0]['title']);
    }
}
