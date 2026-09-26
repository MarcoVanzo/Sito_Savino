<?php

namespace Tests\Feature\Observability;

use App\Enums\EsitoAvviso;
use App\Services\AvvisoTecnico;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Le email dei guasti. Si leggono dal mailer `array` di phpunit.xml e non da
 * Mail::fake(), che ignora in silenzio Mail::raw().
 */
class AvvisoTecnicoTest extends TestCase
{
    /**
     * @return Collection<int, SentMessage>
     */
    public static function inviate(): Collection
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }

    #[Test]
    public function senza_destinatari_non_manda_niente(): void
    {
        config(['services.avvisi.email' => null]);

        $this->assertSame(EsitoAvviso::SenzaDestinatari, app(AvvisoTecnico::class)->invia('Oggetto', 'Testo', 'prova'));
        $this->assertCount(0, self::inviate());
    }

    #[Test]
    public function scrive_a_tutti_gli_indirizzi_validi(): void
    {
        config(['services.avvisi.email' => 'uno@example.com, non-un-indirizzo , due@example.com']);

        $this->assertSame(EsitoAvviso::Inviato, app(AvvisoTecnico::class)->invia('Coda ferma', 'Il worker è fermo', 'prova'));

        $messaggio = self::inviate()->sole()->getOriginalMessage();
        $this->assertSame(['uno@example.com', 'due@example.com'], array_map(fn ($a) => $a->getAddress(), $messaggio->getTo()));
        $this->assertSame('[Sito Savino] Coda ferma', $messaggio->getSubject());
    }

    #[Test]
    public function la_stessa_condizione_non_inonda_la_casella(): void
    {
        config(['services.avvisi.email' => 'uno@example.com']);
        $avviso = app(AvvisoTecnico::class);

        $avviso->invia('A', 'x', 'stessa');
        $this->assertSame(EsitoAvviso::Silenziato, $avviso->invia('A', 'x', 'stessa'));
        $avviso->invia('B', 'x', 'altra');

        $this->assertCount(2, self::inviate());
    }

    #[Test]
    public function il_silenziatore_sopravvive_al_cache_clear_dei_rilasci(): void
    {
        // start.sh esegue `cache:clear` a ogni avvio: con il silenziatore
        // nello store predefinito ogni rilascio rimandava lo stesso avviso.
        config(['services.avvisi.email' => 'uno@example.com']);
        $avviso = app(AvvisoTecnico::class);

        $avviso->invia('A', 'x', 'dopo-il-rilascio');
        Artisan::call('cache:clear');

        $this->assertSame(EsitoAvviso::Silenziato, $avviso->invia('A', 'x', 'dopo-il-rilascio'));
        $this->assertCount(1, self::inviate());
    }

    #[Test]
    public function un_invio_fallito_non_rompe_chi_avvisa(): void
    {
        // Si chiama dai webhook di pagamento e dall'health check: un Resend
        // irraggiungibile non deve far fallire nessuno dei due.
        config(['services.avvisi.email' => 'uno@example.com']);
        Mail::shouldReceive('raw')->andThrow(new RuntimeException('Resend irraggiungibile'));

        $this->assertSame(EsitoAvviso::Fallito, app(AvvisoTecnico::class)->invia('A', 'x', 'rotto'));
    }

    #[Test]
    public function un_invio_fallito_non_silenzia_il_successivo(): void
    {
        // Resend giù per dieci minuti non deve tacere la stessa condizione per
        // tutta l'ora del silenziatore.
        config(['services.avvisi.email' => 'uno@example.com']);
        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Resend irraggiungibile'));
        Mail::shouldReceive('raw')->once();

        $avviso = app(AvvisoTecnico::class);

        $this->assertSame(EsitoAvviso::Fallito, $avviso->invia('A', 'x', 'intermittente'));
        $this->assertSame(EsitoAvviso::Inviato, $avviso->invia('A', 'x', 'intermittente'));
    }
}
