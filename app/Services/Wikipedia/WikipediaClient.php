<?php

namespace App\Services\Wikipedia;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Accesso alle API MediaWiki di Wikipedia.
 *
 * Si legge il wikitesto, non l'HTML: il markup renderizzato cambia a ogni
 * aggiornamento di skin, mentre i template del palmarès (`Pallavolopalm`,
 * `MedaglieOro`, `Med`) sono stabili da anni e già strutturati.
 *
 * Le regole d'uso delle API chiedono uno user agent che identifichi
 * l'applicazione e un contatto: `services.wikipedia.user_agent`.
 */
class WikipediaClient
{
    public function __construct(
        private readonly string $lang,
        private readonly int $timeout,
        private readonly string $userAgent,
    ) {}

    public static function fromConfig(?string $lang = null): self
    {
        return new self(
            $lang ?? (string) config('services.wikipedia.lang', 'it'),
            (int) config('services.wikipedia.timeout', 15),
            (string) config('services.wikipedia.user_agent', 'SavinoDelBeneVolley/1.0'),
        );
    }

    public function lang(): string
    {
        return $this->lang;
    }

    /**
     * Voce completa, seguendo le redirezioni.
     *
     * @return array{title: string, pageid: int, revid: int, wikitext: string}|null
     *                                                                              Null se la voce non esiste.
     */
    public function page(string $title): ?array
    {
        $data = $this->get([
            'action' => 'query',
            'prop' => 'revisions',
            'rvprop' => 'content|ids',
            'rvslots' => 'main',
            'titles' => $title,
            'redirects' => 1,
        ]);

        $page = $data['query']['pages'][0] ?? null;

        if (! is_array($page) || ($page['missing'] ?? false) || ($page['invalid'] ?? false)) {
            return null;
        }

        $revision = $page['revisions'][0] ?? null;
        $content = $revision['slots']['main']['content'] ?? null;

        if (! is_string($content) || $content === '') {
            return null;
        }

        return [
            'title' => (string) $page['title'],
            'pageid' => (int) $page['pageid'],
            'revid' => (int) ($revision['revid'] ?? 0),
            'wikitext' => $content,
        ];
    }

    /**
     * Sola revisione corrente: basta a capire se il palmarès va riletto.
     */
    public function revisionId(string $title): ?int
    {
        $data = $this->get([
            'action' => 'query',
            'prop' => 'revisions',
            'rvprop' => 'ids',
            'titles' => $title,
            'redirects' => 1,
        ]);

        $page = $data['query']['pages'][0] ?? null;

        if (! is_array($page) || ($page['missing'] ?? false)) {
            return null;
        }

        return isset($page['revisions'][0]['revid']) ? (int) $page['revisions'][0]['revid'] : null;
    }

    /**
     * Ricerca a testo pieno: serve quando il nome in anagrafica non coincide
     * con il titolo della voce (Julia Bergmann → Júlia Bergmann).
     *
     * @return list<array{title: string, snippet: string}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $data = $this->get([
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $query,
            'srlimit' => $limit,
        ]);

        $results = $data['query']['search'] ?? [];

        return array_values(array_map(fn (array $row): array => [
            'title' => (string) ($row['title'] ?? ''),
            'snippet' => trim(html_entity_decode(strip_tags((string) ($row['snippet'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ], is_array($results) ? $results : []));
    }

    public function pageUrl(string $title): string
    {
        return sprintf('https://%s.wikipedia.org/wiki/%s', $this->lang, rawurlencode(str_replace(' ', '_', $title)));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(array $query): array
    {
        $query['format'] = 'json';
        $query['formatversion'] = 2;

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Api-User-Agent' => $this->userAgent,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->retry(2, 500, throw: false)
                ->get(sprintf('https://%s.wikipedia.org/w/api.php', $this->lang), $query);
        } catch (ConnectionException $e) {
            throw new WikipediaException("Wikipedia non raggiungibile: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new WikipediaException("Wikipedia ha risposto {$response->status()}.");
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
