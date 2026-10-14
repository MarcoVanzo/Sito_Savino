<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\DeepL\DeepLClient;
use App\Services\DeepL\DeepLException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traduce in inglese, con DeepL, i campi italiani di una notizia.
 *
 * Un campo inglese si scrive solo se è vuoto, se è la copia dell'italiano o
 * se è ancora la traduzione automatica di un italiano che nel frattempo è
 * cambiato. Quello che la redazione ha scritto o corretto a mano non si tocca:
 * lo dice l'impronta salvata in `posts.traduzioni_automatiche`.
 *
 * Il salvataggio fa ripartire l'observer, che accoda di nuovo il job: il
 * secondo giro non trova niente da tradurre e non chiama DeepL.
 */
class TraduciLaNotiziaInInglese implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /** Campi tradotti come testo semplice. */
    private const CAMPI_DI_TESTO = ['title', 'excerpt', 'meta_description'];

    /** Campi con l'HTML dell'editor. */
    private const CAMPI_HTML = ['content'];

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public int $uniqueFor = 600;

    public function __construct(public int $postId) {}

    public function uniqueId(): string
    {
        return (string) $this->postId;
    }

    public function handle(): void
    {
        $deepl = DeepLClient::fromConfig();

        if (! $deepl->configurato()) {
            return;
        }

        $post = Post::find($this->postId);

        if ($post === null) {
            return;
        }

        $daTradurre = $this->campiDaTradurre($post);

        if ($daTradurre === []) {
            return;
        }

        try {
            $tradotti = $this->traduci($deepl, $daTradurre);
        } catch (DeepLException $e) {
            if ($e->quotaEsaurita()) {
                $this->fail($e);

                return;
            }

            throw $e;
        }

        $this->scrivi($daTradurre, $tradotti);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DeepL: notizia non tradotta in inglese, sul sito inglese resta l\'italiano', [
            'post_id' => $this->postId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<string, array{it: string, en: string}>
     */
    private function campiDaTradurre(Post $post): array
    {
        $memoria = $post->traduzioni_automatiche ?? [];
        $campi = [];

        foreach ([...self::CAMPI_DI_TESTO, ...self::CAMPI_HTML] as $campo) {
            $it = trim((string) $post->getTranslation($campo, 'it', false));
            $en = trim((string) $post->getTranslation($campo, 'en', false));

            if ($it === '') {
                continue;
            }

            $automatica = $memoria[$campo] ?? null;

            $daFare = match (true) {
                // L'inglese è ancora quello prodotto da DeepL: si rifà solo se
                // l'italiano è cambiato. Viene prima del confronto con
                // l'italiano: un titolo fatto di soli nomi torna identico, e
                // ritradurlo a ogni giro non finirebbe mai.
                is_array($automatica) && ($automatica['en'] ?? null) === sha1($en) => ($automatica['it'] ?? null) !== sha1($it),
                $en === '', $en === $it => true,
                // Scritto o corretto in redazione.
                default => false,
            };

            if ($daFare) {
                $campi[$campo] = ['it' => $it, 'en' => $en];
            }
        }

        return $campi;
    }

    /**
     * @param  array<string, array{it: string, en: string}>  $campi
     * @return array<string, string>
     */
    private function traduci(DeepLClient $deepl, array $campi): array
    {
        $tradotti = [];

        foreach ([[self::CAMPI_DI_TESTO, false], [self::CAMPI_HTML, true]] as [$gruppo, $html]) {
            $nomi = array_values(array_intersect($gruppo, array_keys($campi)));

            if ($nomi === []) {
                continue;
            }

            $testi = $deepl->traduci(array_map(fn (string $nome): string => $campi[$nome]['it'], $nomi), $html);
            $tradotti += array_combine($nomi, $testi);
        }

        return $tradotti;
    }

    /**
     * Rilegge la notizia sotto lock: un campo cambiato in redazione mentre
     * DeepL traduceva resta com'è (il suo salvataggio ha già accodato un
     * altro giro).
     *
     * @param  array<string, array{it: string, en: string}>  $campi
     * @param  array<string, string>  $tradotti
     */
    private function scrivi(array $campi, array $tradotti): void
    {
        DB::transaction(function () use ($campi, $tradotti): void {
            $post = Post::query()->lockForUpdate()->find($this->postId);

            if ($post === null) {
                return;
            }

            $memoria = $post->traduzioni_automatiche ?? [];
            $scritti = 0;

            foreach ($tradotti as $campo => $inglese) {
                $inglese = trim($inglese);
                $it = trim((string) $post->getTranslation($campo, 'it', false));
                $en = trim((string) $post->getTranslation($campo, 'en', false));

                if ($inglese === '' || $it !== $campi[$campo]['it'] || $en !== $campi[$campo]['en']) {
                    continue;
                }

                $post->setTranslation($campo, 'en', $inglese);
                $memoria[$campo] = ['it' => sha1($it), 'en' => sha1($inglese)];
                $scritti++;
            }

            if ($scritti === 0) {
                return;
            }

            $post->traduzioni_automatiche = $memoria;
            $post->save();
        });
    }
}
