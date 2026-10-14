<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\DeepL\DeepLClient;
use App\Services\DeepL\DeepLException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traduce in inglese, con DeepL, i campi italiani di una notizia pubblicata.
 *
 * Parte un quarto d'ora dopo l'ultimo salvataggio (`services.deepl.attesa_minuti`):
 * la redazione salva spesso mentre sistema un testo, e ogni giro rimanda a
 * DeepL l'intero corpo. Se nel frattempo la notizia è stata salvata ancora, il
 * job si rimette in coda per il nuovo termine; le altre copie accodate le
 * scarta l'unicità. Le bozze non escono dal sito.
 *
 * Un campo inglese si scrive solo se è vuoto, se è la copia dell'italiano o
 * se è ancora la traduzione automatica di un italiano che nel frattempo è
 * cambiato (o è stato svuotato: allora l'inglese si toglie). Quello che la
 * redazione ha scritto o corretto a mano non si tocca: lo dice l'impronta
 * salvata in `posts.traduzioni_automatiche`.
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

    /** Più lungo dell'attesa: il lock non deve scadere mentre il job aspetta in coda. */
    public int $uniqueFor = 3600;

    public function __construct(public int $postId) {}

    /**
     * Accoda la traduzione dopo il quarto d'ora di quiete.
     */
    public static function accoda(int $postId): void
    {
        static::dispatch($postId)->delay(now()->addMinutes(self::attesaMinuti()));
    }

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

        if ($post === null || $post->status !== PostStatus::Published) {
            return;
        }

        $quiete = $post->updated_at?->copy()->addMinutes(self::attesaMinuti());

        if ($quiete !== null && $quiete->isFuture()) {
            static::dispatch($this->postId)->delay($quiete);

            return;
        }

        $daFare = self::campiDaTradurre($post);

        if ($daFare === []) {
            return;
        }

        try {
            $tradotti = $this->traduci($deepl, $daFare);
        } catch (DeepLException $e) {
            if ($e->quotaEsaurita()) {
                $this->fail($e);

                return;
            }

            throw $e;
        }

        $this->scrivi($daFare, $tradotti);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DeepL: notizia non tradotta in inglese, sul sito inglese resta l\'italiano', [
            'post_id' => $this->postId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * I campi su cui il job deve intervenire, con il testo letto adesso.
     * Un italiano vuoto vuol dire togliere l'inglese automatico rimasto.
     *
     * @return array<string, array{it: string, en: string}>
     */
    public static function campiDaTradurre(Post $post): array
    {
        $memoria = $post->traduzioni_automatiche ?? [];
        $campi = [];

        foreach ([...self::CAMPI_DI_TESTO, ...self::CAMPI_HTML] as $campo) {
            $it = trim((string) $post->getTranslation($campo, 'it', false));
            $en = trim((string) $post->getTranslation($campo, 'en', false));
            $automatica = $memoria[$campo] ?? null;
            $ancoraAutomatica = $en !== '' && is_array($automatica) && ($automatica['en'] ?? null) === sha1($en);

            $daFare = match (true) {
                $it === '' => $ancoraAutomatica,
                // L'inglese è ancora quello prodotto da DeepL: si rifà solo se
                // l'italiano è cambiato. Viene prima del confronto con
                // l'italiano: un titolo fatto di soli nomi torna identico, e
                // ritradurlo a ogni giro non finirebbe mai.
                $ancoraAutomatica => ($automatica['it'] ?? null) !== sha1($it),
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

    private static function attesaMinuti(): int
    {
        return max(0, (int) config('services.deepl.attesa_minuti', 15));
    }

    /**
     * @param  array<string, array{it: string, en: string}>  $campi
     * @return array<string, string> Campo => inglese; stringa vuota per i campi da togliere.
     */
    private function traduci(DeepLClient $deepl, array $campi): array
    {
        $tradotti = array_map(fn (): string => '', array_filter($campi, fn (array $testi): bool => $testi['it'] === ''));

        foreach ([[self::CAMPI_DI_TESTO, false], [self::CAMPI_HTML, true]] as [$gruppo, $html]) {
            $nomi = array_values(array_filter(
                $gruppo,
                fn (string $nome): bool => isset($campi[$nome]) && $campi[$nome]['it'] !== '',
            ));

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

                if ($it !== $campi[$campo]['it'] || $en !== $campi[$campo]['en']) {
                    continue;
                }

                if ($it === '') {
                    $post->forgetTranslation($campo, 'en');
                    unset($memoria[$campo]);
                } elseif ($inglese !== '') {
                    $post->setTranslation($campo, 'en', $inglese);
                    $memoria[$campo] = ['it' => sha1($it), 'en' => sha1($inglese)];
                } else {
                    continue;
                }

                $scritti++;
            }

            if ($scritti === 0) {
                return;
            }

            $post->traduzioni_automatiche = $memoria === [] ? null : $memoria;
            $post->save();
        });
    }
}
