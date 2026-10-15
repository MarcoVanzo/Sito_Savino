<?php

namespace Tests\Feature;

use App\Jobs\TraduciLaNotiziaInInglese;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TraduzioneAutomaticaDelleNotizieTest extends TestCase
{
    use RefreshDatabase;

    private static string $privateKey;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $exported);
        self::$privateKey = $exported;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ga4.service_account_json', json_encode([
            'client_email' => 'analytics@savino.iam.gserviceaccount.com',
            'private_key' => self::$privateKey,
            'project_id' => 'savino-analytics',
        ]));
        config()->set('services.google_translate.attiva', true);
    }

    #[Test]
    public function salvare_una_notizia_accoda_la_traduzione_mezz_ora_dopo(): void
    {
        Queue::fake();
        $this->freezeTime();

        $post = Post::factory()->create();

        Queue::assertPushed(TraduciLaNotiziaInInglese::class, fn (TraduciLaNotiziaInInglese $job) => $job->postId === $post->id
            && $job->delay->equalTo(now()->addMinutes(30)));
    }

    #[Test]
    public function senza_interruttore_o_senza_service_account_non_si_accoda_niente(): void
    {
        Queue::fake();

        config()->set('services.google_translate.attiva', false);
        Post::factory()->create();

        config()->set('services.google_translate.attiva', true);
        config()->set('services.ga4.service_account_json', '');
        Post::factory()->create();

        Queue::assertNotPushed(TraduciLaNotiziaInInglese::class);
    }

    #[Test]
    public function traduce_i_campi_inglesi_vuoti_o_copiati_dall_italiano(): void
    {
        $post = $this->notizia([
            'title' => ['it' => 'Vittoria al tie-break', 'en' => 'Vittoria al tie-break'],
            'content' => ['it' => '<p>Gran partita</p>'],
            'excerpt' => ['it' => 'Sintesi'],
        ]);
        $this->fintoGoogle();

        $this->eseguiIlJob($post);

        $post->refresh();
        $this->assertSame('[en] Vittoria al tie-break', $post->getTranslation('title', 'en'));
        $this->assertSame('[en] <p>Gran partita</p>', $post->getTranslation('content', 'en'));
        $this->assertSame('[en] Sintesi', $post->getTranslation('excerpt', 'en'));
        $this->assertSame('', (string) $post->getTranslation('meta_description', 'en', false));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'projects/savino-analytics/locations/global:translateText')
            && $request['mimeType'] === 'text/html'
            && $request['contents'] === ['<p>Gran partita</p>']);
    }

    #[Test]
    public function non_tocca_l_inglese_scritto_dalla_redazione(): void
    {
        $post = $this->notizia([
            'title' => ['it' => 'Vittoria al tie-break', 'en' => 'Tie-break win'],
            'content' => ['it' => '<p>Gran partita</p>', 'en' => '<p>Great match</p>'],
            'excerpt' => ['it' => 'Sintesi', 'en' => 'Summary'],
        ]);
        $this->fintoGoogle();

        $this->eseguiIlJob($post);

        $post->refresh();
        $this->assertSame('Tie-break win', $post->getTranslation('title', 'en'));
        $this->assertSame('<p>Great match</p>', $post->getTranslation('content', 'en'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'translateText'));
    }

    #[Test]
    public function ritraduce_l_inglese_automatico_quando_cambia_l_italiano_ma_non_quello_corretto_a_mano(): void
    {
        $post = $this->notizia([
            'title' => ['it' => 'Vittoria al tie-break'],
            'content' => ['it' => '<p>Gran partita</p>'],
            'excerpt' => ['it' => 'Sintesi'],
        ]);
        $this->fintoGoogle();
        $this->eseguiIlJob($post);

        $post->refresh();
        $post->setTranslation('title', 'it', 'Vittoria in rimonta');
        $post->setTranslation('excerpt', 'it', 'Sintesi nuova');
        $post->setTranslation('excerpt', 'en', 'Summary fixed by hand');
        $post->saveQuietly();

        $this->eseguiIlJob($post);

        $post->refresh();
        $this->assertSame('[en] Vittoria in rimonta', $post->getTranslation('title', 'en'));
        $this->assertSame('Summary fixed by hand', $post->getTranslation('excerpt', 'en'));
        $this->assertSame('[en] <p>Gran partita</p>', $post->getTranslation('content', 'en'));
    }

    #[Test]
    public function un_job_superato_da_un_salvataggio_successivo_non_traduce(): void
    {
        $post = $this->notizia(['title' => ['it' => 'Prima versione']]);
        $vecchio = new TraduciLaNotiziaInInglese($post->id, TraduciLaNotiziaInInglese::improntaDellItaliano($post));

        $post->setTranslation('title', 'it', 'Seconda versione');
        $post->saveQuietly();
        $this->fintoGoogle();

        $vecchio->handle();

        Http::assertNothingSent();
        $this->assertSame('', (string) $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function il_salvataggio_del_job_non_riaccoda_un_altra_traduzione(): void
    {
        $post = $this->notizia(['title' => ['it' => 'Vittoria al tie-break']]);
        $this->fintoGoogle();
        Queue::fake();

        $this->eseguiIlJob($post);

        Queue::assertNotPushed(TraduciLaNotiziaInInglese::class);
    }

    /**
     * Notizia salvata senza far partire l'observer: i test chiamano il job da sé.
     *
     * @param  array<string, array<string, string>>  $testi
     */
    private function notizia(array $testi): Post
    {
        $post = Post::factory()->make(['content' => '', 'excerpt' => '']);

        foreach ($testi as $campo => $traduzioni) {
            $post->setTranslations($campo, $traduzioni);
        }

        $post->saveQuietly();

        return $post;
    }

    private function eseguiIlJob(Post $post): void
    {
        (new TraduciLaNotiziaInInglese($post->id, TraduciLaNotiziaInInglese::improntaDellItaliano($post->refresh())))->handle();
    }

    private function fintoGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-di-prova']),
            'translation.googleapis.com/*' => fn (Request $request) => Http::response([
                'translations' => array_map(fn (string $testo) => ['translatedText' => '[en] '.$testo], $request['contents']),
            ]),
        ]);
    }
}
