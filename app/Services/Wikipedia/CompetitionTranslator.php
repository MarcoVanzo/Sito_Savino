<?php

namespace App\Services\Wikipedia;

use Illuminate\Support\Str;

/**
 * Traduce in inglese i nomi di competizione e i premi individuali letti da
 * it.wikipedia, usando il dizionario in `config/palmares.php`.
 *
 * Quello che non è in dizionario resta in italiano: una traduzione inventata
 * sarebbe peggio del nome originale, e la riga è comunque modificabile in
 * redazione.
 */
class CompetitionTranslator
{
    /** @var array<string, string>|null */
    private ?array $competitions = null;

    /** @var array<string, string>|null */
    private ?array $awards = null;

    public function competition(string $italian): string
    {
        $this->boot();

        // Le categorie giovanili non stanno in dizionario una per una: si
        // stacca il suffisso, si traduce la competizione e lo si riattacca
        // nella forma inglese ("Campionato europeo under 19" → "European
        // Championship U19").
        if (preg_match('/^(.*?)\s+under\s*(\d{2})$/iu', trim($italian), $m) === 1) {
            $base = $this->lookup($this->competitions, $m[1]);

            return $base !== null ? "{$base} U{$m[2]}" : $italian;
        }

        return $this->lookup($this->competitions, $italian) ?? $italian;
    }

    public function award(?string $italian): ?string
    {
        if ($italian === null || trim($italian) === '') {
            return $italian;
        }

        $this->boot();

        return $this->lookup($this->awards, $italian) ?? $italian;
    }

    /**
     * @param  array<string, string>  $dictionary
     */
    private function lookup(array $dictionary, string $term): ?string
    {
        return $dictionary[$this->normalize($term)] ?? null;
    }

    private function normalize(string $term): string
    {
        return Str::of($term)->ascii()->lower()->squish()->toString();
    }

    private function boot(): void
    {
        if ($this->competitions !== null) {
            return;
        }

        $this->competitions = $this->normalizeKeys((array) config('palmares.competitions', []));
        $this->awards = $this->normalizeKeys((array) config('palmares.awards', []));
    }

    /**
     * @param  array<array-key, mixed>  $dictionary
     * @return array<string, string>
     */
    private function normalizeKeys(array $dictionary): array
    {
        $normalized = [];

        foreach ($dictionary as $italian => $english) {
            $normalized[$this->normalize((string) $italian)] = (string) $english;
        }

        return $normalized;
    }
}
