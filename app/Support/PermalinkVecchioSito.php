<?php

namespace App\Support;

use App\Models\Post;

/**
 * Traduce un permalink del vecchio sito WordPress nell'indirizzo di oggi.
 *
 * Su WordPress le notizie stavano alla radice del dominio
 * (`/savino-del-bene-volley-e-claro-italia-insieme…/`), qui stanno sotto
 * `/news/{slug}`. Lo slug però è lo stesso: l'import l'ha conservato, e su 2988
 * permalink del sitemap di Yoast 938 corrispondono a un post pubblicato. Sono
 * link indicizzati, salvati dagli aggregatori e condivisi sui social: al
 * passaggio del dominio cadrebbero tutti sulla rotta generica delle pagine CMS,
 * che risponde 404.
 *
 * Non serve una tabella di redirect: la corrispondenza è lo slug, che vive già
 * su `posts`. Una tabella sarebbe una seconda copia da tenere allineata a ogni
 * slug che la redazione corregge, e andrebbe stale in silenzio.
 *
 * Quello che non si riconosce resta 404, e non diventa un 301 di massa verso
 * `/news`: i 2048 permalink rimasti sono l'archivio 2014-2021 che non è mai
 * stato importato, e mandarli tutti su una pagina generica è esattamente il
 * soft 404 che Google scarta — senza nemmeno il vantaggio di far vedere il
 * buco.
 */
class PermalinkVecchioSito
{
    /**
     * L'indirizzo della notizia che stava su quello slug, o null.
     *
     * Si chiama solo quando nessuna pagina del CMS risponde a quello slug, in
     * modo che una pagina e una notizia omonime non si rubino l'indirizzo: in
     * produzione è il caso di `cartelle-stampa`, che è sia una pagina della
     * sezione Comunicazione sia un vecchio comunicato. Vince la pagina, che è
     * il contenuto vivo.
     */
    public static function perLoSlug(string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }

        $esiste = Post::published()->where('slug', $slug)->exists();

        return $esiste ? self::indirizzoDellaNotizia($slug) : null;
    }

    /**
     * L'indirizzo della notizia a partire dall'identificativo WordPress.
     *
     * `?p=<id>` è la forma con cui WordPress indirizza un post senza passare
     * dallo slug, ed è quella che il vecchio feed pubblica come `guid`: è la
     * chiave su cui gli aggregatori — la Lega fra questi — riconoscono una
     * notizia già vista. Chi ha salvato il guid come link arriva qui.
     */
    public static function perWpId(int $wpId): ?string
    {
        if ($wpId <= 0) {
            return null;
        }

        $slug = Post::published()->where('wp_id', $wpId)->value('slug');

        return is_string($slug) ? self::indirizzoDellaNotizia($slug) : null;
    }

    private static function indirizzoDellaNotizia(string $slug): string
    {
        return route(self::prefissoDiRotta().'news.show', ['slug' => $slug]);
    }

    /**
     * Il prefisso che `web.php` mette ai nomi delle rotte della lingua: vuoto
     * per quella predefinita, `en.` per l'inglese.
     *
     * Il confronto e' con `app.fallback_locale` e non con `app.locale`:
     * `App::setLocale()` riscrive `app.locale` nella configurazione, quindi a
     * richiesta in corso quest'ultima vale sempre la lingua corrente e il
     * confronto sarebbe sempre vero. Su `/en` il redirect perdeva il prefisso
     * e mandava la notizia inglese sull'indirizzo italiano.
     */
    private static function prefissoDiRotta(): string
    {
        $locale = app()->getLocale();

        return $locale === config('app.fallback_locale') ? '' : $locale.'.';
    }
}
