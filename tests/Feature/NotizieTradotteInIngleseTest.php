<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Jobs\TraduciLaNotiziaInInglese;
use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\UniqueLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotizieTradotteInIngleseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // In coda vera il job aspetta un quarto d'ora dall'ultimo salvataggio;
        // qui la coda è sincrona e l'attesa si prova a parte.
        config(['services.deepl.key' => 'chiave-di-prova:fx', 'services.deepl.attesa_minuti' => 0]);
        Http::preventStrayRequests();
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
    public function un_testo_che_deepl_lascia_uguale_non_si_ritraduce_ai_salvataggi_dopo(): void
    {
        $this->deeplTraduce(fn (string $testo): string => $testo);

        $post = Post::factory()->create([
            'title' => ['it' => 'Savino Del Bene - Vero Volley'],
            'content' => null,
            'excerpt' => null,
        ]);

        $post->refresh()->setTranslation('excerpt', 'it', 'Si gioca domenica')->save();

        $titoliMandati = collect(Http::recorded())
            ->filter(fn (array $coppia): bool => in_array('Savino Del Bene - Vero Volley', $coppia[0]['text'], true))
            ->count();
        $this->assertSame(1, $titoliMandati);
        $this->assertSame('Savino Del Bene - Vero Volley', $post->refresh()->getTranslation('title', 'en', false));
        $this->assertSame('Si gioca domenica', $post->getTranslation('excerpt', 'en', false));
    }

    #[Test]
    public function le_bozze_non_vanno_a_deepl_finche_non_si_pubblicano(): void
    {
        $this->deeplTraduce(fn (string $testo): string => "EN {$testo}");

        $post = Post::factory()->draft()->create([
            'title' => ['it' => 'Arriva una nuova schiacciatrice'],
            'content' => null,
            'excerpt' => null,
        ]);

        Http::assertNothingSent();

        $post->refresh()->update(['status' => PostStatus::Published, 'published_at' => now()]);

        $this->assertSame('EN Arriva una nuova schiacciatrice', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function svuotare_l_italiano_toglie_l_inglese_automatico(): void
    {
        $this->deeplTraduce(fn (string $testo): string => "EN {$testo}");
        $post = $this->notiziaInItaliano();

        $post->refresh()->setTranslation('excerpt', 'it', '')->save();

        $this->assertSame('', $post->refresh()->getTranslation('excerpt', 'en', false));
        $this->assertSame('EN Vittoria a Conegliano', $post->getTranslation('title', 'en', false));
    }

    #[Test]
    public function cambiare_solo_l_inglese_non_accoda_nessuna_traduzione(): void
    {
        Queue::fake();
        $post = Post::factory()->create(['title' => ['it' => 'Vittoria', 'en' => 'Win']]);
        // Senza il lock un accodamento non verrebbe scartato: il test vede davvero l'observer.
        $this->rilasciaILock([$post->id]);
        Queue::fake();

        $post->refresh()->setTranslation('title', 'en', 'Great win')->save();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function la_traduzione_aspetta_un_quarto_d_ora_dall_ultimo_salvataggio(): void
    {
        config(['services.deepl.attesa_minuti' => 15]);
        Queue::fake();
        $this->freezeTime();

        $post = Post::factory()->create(['title' => ['it' => 'Vittoria']]);

        Queue::assertPushed(TraduciLaNotiziaInInglese::class, fn (TraduciLaNotiziaInInglese $job): bool => $job->postId === $post->id
            && $job->delay->equalTo(now()->addMinutes(15)));

        // Il job arriva, ma la notizia è stata salvata ancora 5 minuti fa:
        // si rimette in coda per il nuovo termine senza chiamare DeepL.
        $this->travel(15)->minutes();
        $this->rilasciaILock([$post->id]);
        $post->refresh()->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();
        Http::fake();

        (new TraduciLaNotiziaInInglese($post->id))->handle();

        Http::assertNothingSent();
        Queue::assertPushed(TraduciLaNotiziaInInglese::class, fn (TraduciLaNotiziaInInglese $job): bool => $job->delay->timestamp === now()->addMinutes(10)->timestamp);
    }

    #[Test]
    public function la_traduzione_non_scrive_nel_registro_della_redazione(): void
    {
        $this->deeplTraduce(fn (string $testo): string => "EN {$testo}");

        $this->notiziaInItaliano();

        $this->assertSame(0, ActivityLog::query()->where('model_type', Post::class)->count());
    }

    #[Test]
    public function salvare_due_volte_dal_pannello_non_riporta_indietro_l_inglese(): void
    {
        $this->deeplTraduce(fn (string $testo): string => "EN {$testo}");
        $post = $this->notiziaInItaliano();

        $pagina = Livewire::actingAs($this->redattore())
            ->test(EditPost::class, ['record' => $post->getRouteKey()]);

        // Primo salvataggio: l'italiano cambia e il job ritraduce mentre la
        // pagina resta aperta con l'inglese di prima.
        $pagina->fillForm(['title' => 'Vittoria netta a Conegliano'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('EN Vittoria netta a Conegliano', $post->refresh()->getTranslation('title', 'en', false));

        // Secondo salvataggio dalla stessa pagina.
        $pagina->fillForm(['excerpt' => 'Tre punti pesanti'])->call('save')->assertHasNoFormErrors();

        $post->refresh();
        $this->assertSame('EN Vittoria netta a Conegliano', $post->getTranslation('title', 'en', false));
        $this->assertSame('EN Tre punti pesanti', $post->getTranslation('excerpt', 'en', false));
    }

    #[Test]
    public function l_inglese_scritto_dal_pannello_resta_quello_della_redazione(): void
    {
        $this->deeplTraduce(fn (string $testo): string => "EN {$testo}");
        $post = $this->notiziaInItaliano();

        Livewire::actingAs($this->redattore())
            ->test(EditPost::class, ['record' => $post->getRouteKey()])
            ->set('activeLocale', 'en')
            ->fillForm(['title' => 'Away win in Conegliano'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Away win in Conegliano', $post->refresh()->getTranslation('title', 'en', false));
    }

    #[Test]
    public function il_comando_notturno_rimette_in_coda_solo_le_notizie_recenti_senza_inglese(): void
    {
        Queue::fake();
        $recente = Post::factory()->create(['title' => ['it' => 'Recente'], 'published_at' => now()->subDays(3)]);
        Post::factory()->create(['title' => ['it' => 'Vecchia'], 'published_at' => now()->subDays(90)]);
        Post::factory()->create(['title' => ['it' => 'Tradotta', 'en' => 'Translated'], 'excerpt' => null, 'content' => null, 'published_at' => now()->subDay()]);
        $this->rilasciaILock([$recente->id]);
        Queue::fake();

        $this->artisan('notizie:traduci-in-inglese')->expectsOutputToContain('Accodate: 1 notizie.')->assertSuccessful();

        Queue::assertPushed(TraduciLaNotiziaInInglese::class, 1);
        Queue::assertPushed(TraduciLaNotiziaInInglese::class, fn (TraduciLaNotiziaInInglese $job): bool => $job->postId === $recente->id);
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

    /**
     * Il worker toglie il lock di unicità quando comincia il job: con la coda
     * finta nessuno lo fa, e un secondo accodamento verrebbe scartato.
     *
     * @param  list<int>  $postIds
     */
    private function rilasciaILock(array $postIds): void
    {
        foreach ($postIds as $postId) {
            (new UniqueLock(Cache::driver()))->release(new TraduciLaNotiziaInInglese($postId));
        }
    }

    private function redattore(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user;
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
