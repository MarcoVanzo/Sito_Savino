<?php

namespace App\Support;

/**
 * Traduce il link di una diretta nell'indirizzo da mettere dentro l'iframe.
 *
 * L'indirizzo arriva dal pannello e finisce in un `<iframe>`: si accettano solo
 * le piattaforme conosciute, e solo nella forma "embed" che quelle piattaforme
 * pubblicano. Un dominio qualsiasi verrebbe caricato dentro al sito con i
 * permessi della pagina, quindi chi non è in elenco viene aperto in una scheda
 * nuova invece che incorporato.
 */
class LiveStream
{
    public static function embedUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        return match (true) {
            $host === 'youtu.be' => self::youtube(trim($path, '/')),
            in_array($host, ['youtube.com', 'm.youtube.com'], true) => self::youtubeFromUrl($path, $query),
            $host === 'vimeo.com' => self::vimeo(trim($path, '/')),
            $host === 'player.vimeo.com' => $url,
            in_array($host, ['twitch.tv', 'm.twitch.tv'], true) => self::twitch(trim($path, '/')),
            $host === 'dailymotion.com' => self::dailymotion(trim($path, '/')),
            default => null,
        };
    }

    private static function youtubeFromUrl(string $path, array $query): ?string
    {
        if (str_starts_with($path, '/embed/')) {
            return 'https://www.youtube.com/embed/'.substr($path, 7);
        }

        if (str_starts_with($path, '/live/')) {
            return self::youtube(substr($path, 6));
        }

        return self::youtube((string) ($query['v'] ?? ''));
    }

    private static function youtube(string $id): ?string
    {
        $id = trim(explode('?', $id)[0], '/');

        return preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) === 1
            ? 'https://www.youtube.com/embed/'.$id.'?autoplay=1'
            : null;
    }

    private static function vimeo(string $id): ?string
    {
        return preg_match('/^\d+$/', $id) === 1
            ? 'https://player.vimeo.com/video/'.$id
            : null;
    }

    private static function twitch(string $channel): ?string
    {
        if (preg_match('/^[A-Za-z0-9_]{3,}$/', $channel) !== 1) {
            return null;
        }

        // Twitch pretende il dominio che ospita l'iframe, altrimenti rifiuta.
        $parent = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return 'https://player.twitch.tv/?channel='.$channel.'&parent='.$parent;
    }

    private static function dailymotion(string $path): ?string
    {
        return preg_match('#^video/([A-Za-z0-9]+)#', $path, $m) === 1
            ? 'https://www.dailymotion.com/embed/video/'.$m[1]
            : null;
    }
}
