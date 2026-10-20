<?php

namespace App\Support;

/**
 * Il `<head>` reso dal server SSR (i `<Head>` delle pagine) e quello di
 * app.blade.php, senza doppioni.
 *
 * Titolo, description e og: li stampa sempre il layout, che parte dai meta
 * calcolati sul server (ServeSocialCrawlerMeta): con l'SSR acceso ogni pagina
 * aveva due `<title>` e due description. Dall'SSR si prende solo il valore,
 * quando il server non ne ha uno suo (una pagina che ServeSocialCrawlerMeta
 * non descrive ha comunque il titolo che le da' il suo `<Head>`); il resto
 * del suo head (robots, link) passa com'e'.
 */
class TestaDellSsr
{
    /** I meta che stampa il layout: dall'SSR si toglie il doppione. */
    private const META_DEL_LAYOUT = [
        'description', 'og:title', 'og:description', 'og:image', 'og:url', 'og:type', 'og:locale', 'og:site_name',
        'twitter:card', 'twitter:title', 'twitter:description', 'twitter:image',
    ];

    /**
     * @param  list<string>|string|null  $head
     * @return array{title: string|null, meta: array<string, string>, resto: string}
     */
    public static function separa(array|string|null $head): array
    {
        $html = is_array($head) ? implode("\n", $head) : (string) $head;
        $titolo = null;
        $meta = [];

        $html = preg_replace_callback('#<title\b[^>]*>(.*?)</title>#is', function (array $pezzi) use (&$titolo): string {
            $titolo ??= self::testo($pezzi[1]);

            return '';
        }, $html) ?? $html;

        $html = preg_replace_callback('#<meta\b[^>]*>#i', function (array $pezzi) use (&$meta): string {
            $tag = $pezzi[0];
            $nome = self::attributo($tag, 'name') ?? self::attributo($tag, 'property');

            if ($nome === null || ! in_array($nome, self::META_DEL_LAYOUT, true)) {
                return $tag;
            }

            $meta[$nome] ??= self::attributo($tag, 'content') ?? '';

            return '';
        }, $html) ?? $html;

        return [
            'title' => $titolo !== '' ? $titolo : null,
            'meta' => array_filter($meta, fn (string $valore) => $valore !== ''),
            'resto' => trim((string) preg_replace("/\n{2,}/", "\n", $html)),
        ];
    }

    private static function attributo(string $tag, string $nome): ?string
    {
        if (preg_match('#\s'.preg_quote($nome, '#').'\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag, $pezzi) !== 1) {
            return null;
        }

        return self::testo($pezzi[2] !== '' ? $pezzi[2] : ($pezzi[3] ?? ''));
    }

    /** Il valore come testo: Blade lo riescapa quando lo stampa. */
    private static function testo(string $html): string
    {
        return trim(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
