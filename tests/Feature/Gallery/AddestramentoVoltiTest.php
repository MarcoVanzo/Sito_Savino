<?php

namespace Tests\Feature\Gallery;

use App\Filament\Actions\TrainAiFacesAction;
use App\Models\Player;
use App\Models\StaffMember;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'addestramento del riconoscitore su una persona dell'anagrafica.
 *
 * Due promesse fatte a chi carica le foto: che le immagini non restino sul
 * server dopo l'invio — lo dice il testo sotto al campo — e che, quando una
 * foto viene scartata, il pannello spieghi il perché in italiano invece di
 * mostrare il messaggio grezzo di CompreFace.
 */
class AddestramentoVoltiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.compreface.host' => 'http://compreface.test:8000']);
        config(['services.compreface.key' => 'chiave-di-prova']);
    }

    private function fotoDiProva(string $nome): string
    {
        Storage::disk('local')->put('temp_ai_training/'.$nome, 'contenuto della foto');

        return 'temp_ai_training/'.$nome;
    }

    private function rispostaAddestramento(int $stato, array $corpo = []): void
    {
        Http::fake([
            '*/subjects' => Http::response(['message' => 'ok'], 201),
            '*/faces*' => Http::response($corpo, $stato),
        ]);
    }

    #[Test]
    public function il_modulo_chiede_almeno_una_foto(): void
    {
        $campi = TrainAiFacesAction::formSchema();

        $this->assertCount(1, $campi);
        $this->assertSame('training_images', $campi[0]->getName());
        $this->assertTrue($campi[0]->isRequired());
        $this->assertTrue($campi[0]->isMultiple());
    }

    /**
     * "Non verranno salvate sul server": il file temporaneo sparisce appena
     * la foto è stata inviata.
     */
    #[Test]
    public function le_foto_di_addestramento_non_restano_sul_server(): void
    {
        $this->rispostaAddestramento(201, ['image_id' => 'abc']);
        $atleta = Player::factory()->create();
        $percorso = $this->fotoDiProva('volto.jpg');

        $this->assertTrue(Storage::disk('local')->exists($percorso));

        TrainAiFacesAction::execute($atleta, ['training_images' => [$percorso]]);

        $this->assertFalse(
            Storage::disk('local')->exists($percorso),
            'La foto doveva essere cancellata dopo l\'invio.'
        );
    }

    #[Test]
    public function il_soggetto_viene_creato_prima_di_mandare_le_foto(): void
    {
        $this->rispostaAddestramento(201, ['image_id' => 'abc']);
        $atleta = Player::factory()->create();

        TrainAiFacesAction::execute($atleta, ['training_images' => [$this->fotoDiProva('volto.jpg')]]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/subjects'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/faces'));
    }

    #[Test]
    public function senza_foto_non_si_manda_niente_da_imparare(): void
    {
        $this->rispostaAddestramento(201);
        $atleta = Player::factory()->create();

        TrainAiFacesAction::execute($atleta, []);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/faces'));
    }

    /**
     * CompreFace risponde in inglese e per sigle: il pannello deve dire cosa
     * fare, non ripetere il messaggio del servizio.
     */
    #[Test]
    public function i_motivi_dello_scarto_sono_spiegati_in_italiano(): void
    {
        NotificationFacade::fake();
        $this->rispostaAddestramento(400, ['message' => 'More than one face is found in the image']);
        $atleta = Player::factory()->create();

        TrainAiFacesAction::execute($atleta, ['training_images' => [$this->fotoDiProva('gruppo.jpg')]]);

        $this->assertNotNull(
            $this->ultimaNotifica(),
            'Doveva arrivare una notifica di esito.'
        );
        $this->assertStringContainsString('primo piano', $this->ultimaNotifica());
    }

    #[Test]
    public function una_foto_senza_volti_lo_dice(): void
    {
        $this->rispostaAddestramento(400, ['message' => 'No face is found in the given image']);
        $atleta = Player::factory()->create();

        TrainAiFacesAction::execute($atleta, ['training_images' => [$this->fotoDiProva('paesaggio.jpg')]]);

        $this->assertStringContainsString('Nessun volto trovato', $this->ultimaNotifica());
    }

    #[Test]
    public function l_addestramento_vale_anche_per_lo_staff(): void
    {
        $this->rispostaAddestramento(201, ['image_id' => 'abc']);
        $membro = StaffMember::factory()->create();

        TrainAiFacesAction::execute($membro, ['training_images' => [$this->fotoDiProva('volto.jpg')]]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/subjects'));
        $this->assertStringContainsString('appresi', $this->ultimaNotifica());
    }

    private function ultimaNotifica(): ?string
    {
        $notifiche = session()->get('filament.notifications', []);
        $ultima = end($notifiche);

        if ($ultima === false) {
            return null;
        }

        return is_array($ultima)
            ? ($ultima['body'] ?? '')
            : (string) ($ultima instanceof Notification ? $ultima->getBody() : '');
    }
}
