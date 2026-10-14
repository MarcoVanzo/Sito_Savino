<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotizieTradotteInIngleseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.deepl.key' => 'chiave-di-prova:fx']);
    }

    #[Test]
    public function una_notizia_nuova_esce_con_l_inglese_tradotto(): void
    {
        $this->deeplTraduce(fn (string $testo): string => "EN {$testo}");

        $post = $this->notiziaInItaliano();

        $post->refresh();
        $this->assertSame('EN Vittoria a Conegliano', $post->getTranslation('title', 'en', false));
        $this->assertSame('EN Tre punti in trasferta', $post->getTranslation('excerpt', 'en', false));
        $this->assertSame('EN <p>Le ragazze <strong>vincono</strong></p>', $post->getTranslation('content', 'en', false));
        $this->assertSame('Vittoria a Conegliano', $post->getTranslation('title', 'it', false));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-free.deepl.com/v2/translate'
            && $request->header('Authorization')[0] === 'DeepL-Auth-Key chiave-di-prova:fx'
            && $request['source_lang'] === 'IT'
            && $request['target_lang'] === 'EN-GB'
            && ($request['tag_handling'] ?? null) === 'html'
            && $request['text'] === ['<p>Le ragazze <strong>vincono</strong></p>']);
    }

    #[Test]
    public function cambiare_l_italiano_ritraduce_l_inglese_automatico(): void
    {
        $this->deeplTraduce(fn (string $testo): string => "EN {$testo}");
        $post = $this->notiziaInItaliano();

        $post->refresh()->setTranslation('title', 'it', 'Sconfitta a Conegliano')->save();

        $this->assertSame('EN Sconfitta a Conegliano', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function l_inglese_corretto_in_redazione_non_si_tocca(): void
    {
        $this->deeplTraduce(fn (string $testo): string => "EN {$testo}");
        $post = $this->notiziaInItaliano();
        $post->refresh()->setTranslation('title', 'en', 'Win in Conegliano')->save();

        $post->refresh()
            ->setTranslation('title', 'it', 'Grande vittoria a Conegliano')
            ->setTranslation('excerpt', 'it', 'Tre punti pesanti')
            ->save();

        $post->refresh();
        $this->assertSame('Win in Conegliano', $post->getTranslation('title', 'en', false));
        $this->assertSame('EN Tre punti pesanti', $post->getTranslation('excerpt', 'en', false));
    }

    #[Test]
    public function un_testo_che_deepl_lascia_uguale_non_si_ritraduce_a_ogni_salvataggio(): void
    {
        $this->deeplTraduce(fn (string $testo): string => $testo);

        $post = Post::factory()->create([
            'title' => ['it' => 'Savino Del Bene - Vero Volley'],
            'content' => null,
            'excerpt' => null,
        ]);
        $chiamate = count(Http::recorded());

        $post->refresh()->update(['status' => $post->status]);
        $post->refresh()->touch();

        $this->assertSame(1, $chiamate);
        $this->assertCount(1, Http::recorded());
        $this->assertSame('Savino Del Bene - Vero Volley', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function senza_chiave_deepl_non_parte_nessuna_traduzione(): void
    {
        config(['services.deepl.key' => null]);
        Http::fake();

        $post = $this->notiziaInItaliano();

        Http::assertNothingSent();
        $this->assertSame('', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function con_la_quota_esaurita_la_notizia_si_salva_e_resta_in_italiano(): void
    {
        Http::fake(['api-free.deepl.com/*' => Http::response(['message' => 'Quota exceeded'], 456)]);
        Log::spy();

        $post = $this->notiziaInItaliano();

        $this->assertSame('', $post->refresh()->getTranslation('title', 'en', false));
        Log::shouldHaveReceived('error')->withArgs(fn (string $messaggio): bool => str_contains($messaggio, 'DeepL'));
    }

    private function notiziaInItaliano(): Post
    {
        return Post::factory()->create([
            'title' => ['it' => 'Vittoria a Conegliano'],
            'excerpt' => ['it' => 'Tre punti in trasferta'],
            'content' => ['it' => '<p>Le ragazze <strong>vincono</strong></p>'],
            'meta_description' => null,
        ]);
    }

    /**
     * @param  callable(string): string  $traduzione
     */
    private function deeplTraduce(callable $traduzione): void
    {
        Http::fake(['api-free.deepl.com/*' => fn (Request $request) => Http::response([
            'translations' => array_map(
                fn (string $testo): array => ['detected_source_language' => 'IT', 'text' => $traduzione($testo)],
                $request['text'],
            ),
        ])]);
    }
}
