<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\GoogleTranslate\GoogleTranslateClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Traduce in inglese una notizia mezz'ora dopo l'ultimo salvataggio.
 *
 * Il job nasce con l'impronta dell'italiano del momento in cui è stato
 * accodato: se nel frattempo la redazione ha cambiato il testo, ne è già
 * partito un altro e questo non fa niente. Così si traduce una volta sola,
 * a correzioni finite.
 *
 * Un campo inglese si scrive solo se è vuoto, se è la copia dell'italiano o
 * se l'aveva scritto questo job e l'italiano è cambiato. Un inglese corretto
 * a mano dalla redazione non si tocca più.
 */
class TraduciLaNotiziaInInglese implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Campi tradotti come testo semplice. */
    private const CAMPI_DI_TESTO = ['title', 'excerpt', 'meta_description', 'testo_alternativo'];

    /** Campi tradotti come HTML: Google lascia stare tag e attributi. */
    private const CAMPI_HTML = ['content'];

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 600];

    public function __construct(
        public readonly int $postId,
        public readonly string $impronta,
    ) {}

    /**
     * Accoda la traduzione se l'italiano è cambiato da quella precedente.
     */
    public static function richiediPer(Post $post): void
    {
        if (! config('services.google_translate.attiva') || GoogleTranslateClient::fromConfig() === null) {
            return;
        }

        $impronta = self::improntaDellItaliano($post);

        if ($impronta === ($post->traduzioni_automatiche['impronta'] ?? null)) {
            return;
        }

        self::dispatch($post->getKey(), $impronta)
            ->delay(now()->addMinutes((int) config('services.google_translate.ritardo_minuti', 30)));
    }

    public static function improntaDellItaliano(Post $post): string
    {
        $italiano = [];

        foreach ([...self::CAMPI_DI_TESTO, ...self::CAMPI_HTML] as $campo) {
            $italiano[$campo] = self::testo($post, $campo, 'it');
        }

        return hash('xxh128', (string) json_encode($italiano));
    }

    public function handle(): void
    {
        $client = GoogleTranslateClient::fromConfig();
        $post = Post::find($this->postId);

        if ($client === null || $post === null || self::improntaDellItaliano($post) !== $this->impronta) {
            return;
        }

        /** @var array{impronta?: string, campi?: array<string, array{it: string, en: string}>} $stato */
        $stato = $post->traduzioni_automatiche ?? [];
        $campi = $stato['campi'] ?? [];

        $traduzioni = [
            ...$client->traduci($this->daTradurre($post, self::CAMPI_DI_TESTO, $campi), 'it', 'en'),
            ...$client->traduci($this->daTradurre($post, self::CAMPI_HTML, $campi), 'it', 'en', 'text/html'),
        ];

        foreach ($traduzioni as $campo => $inglese) {
            $post->setTranslation($campo, 'en', $inglese);
            $campi[$campo] = [
                'it' => hash('xxh128', self::testo($post, $campo, 'it')),
                'en' => hash('xxh128', $inglese),
            ];
        }

        $post->traduzioni_automatiche = ['impronta' => $this->impronta, 'campi' => $campi];
        $post->save();

        if ($traduzioni !== []) {
            Log::info('Notizia tradotta in inglese', ['post' => $post->getKey(), 'campi' => array_keys($traduzioni)]);
        }
    }

    /**
     * @param  list<string>  $campi
     * @param  array<string, array{it: string, en: string}>  $giaTradotti
     * @return array<string, string>
     */
    private function daTradurre(Post $post, array $campi, array $giaTradotti): array
    {
        $testi = [];

        foreach ($campi as $campo) {
            $italiano = self::testo($post, $campo, 'it');
            $inglese = self::testo($post, $campo, 'en');

            if (trim(strip_tags($italiano)) === '') {
                continue;
            }

            $precedente = $giaTradotti[$campo] ?? null;
            $scrittoDalJob = $precedente !== null && hash('xxh128', $inglese) === $precedente['en'];
            $italianoCambiato = $precedente === null || hash('xxh128', $italiano) !== $precedente['it'];

            if (trim($inglese) === '' || $inglese === $italiano || ($scrittoDalJob && $italianoCambiato)) {
                $testi[$campo] = $italiano;
            }
        }

        return $testi;
    }

    private static function testo(Post $post, string $campo, string $lingua): string
    {
        $valore = $post->getTranslation($campo, $lingua, false);

        return is_string($valore) ? $valore : '';
    }
}
