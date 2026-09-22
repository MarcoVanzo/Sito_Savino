<?php

namespace App\Services\VecchioSito;

/**
 * Pulisce il contenuto che arriva da WordPress.
 *
 * E' il pulitore con cui sono entrate le 941 notizie dell'archivio, rimesso in
 * piedi quando il sito nuovo ha dovuto riprendere i comunicati di luglio,
 * agosto e settembre: era stato tolto dando la migrazione per conclusa. Le
 * regole non si toccano a cuor leggero — un comunicato di settembre pulito in
 * modo diverso da uno di giugno si riconosce a occhio nella stessa pagina.
 *
 * Toglie quello che l'editor di WordPress si porta dietro e il sito non usa:
 * stili in linea, `<span>` di sola formattazione, classi `wp-*`, commenti di
 * Gutenberg.
 */
class PulitoreDelContenuto
{
    /** Il dominio da cui si riconoscono i link interni. */
    private const DOMINIO = 'savinodelbenevolley.it';

    /**
     * Gli slug delle notizie che esistono da noi.
     *
     * Serve a decidere cosa fare di un link a un altro comunicato: se la
     * notizia c'e', il link diventa interno; se non c'e', resta il testo senza
     * link, perche' mandare il lettore su un dominio che sta per cambiare
     * padrone e' peggio che non mandarlo da nessuna parte.
     *
     * @var array<string, true>
     */
    private array $slugConosciuti = [];

    /**
     * @param  list<string>  $slug
     */
    public function conosceGliSlug(array $slug): self
    {
        $this->slugConosciuti = array_fill_keys($slug, true);

        return $this;
    }

    /**
     * La pulizia completa di un contenuto.
     */
    public function contenuto(string $html): string
    {
        $html = $this->togliICommenti($html);
        $html = $this->togliGliSpanDiFormattazione($html);
        $html = $this->togliGliAllineamenti($html);
        $html = $this->togliGliStiliInLinea($html);
        $html = $this->togliLeClassiDiWordPress($html);
        $html = $this->riscriviILinkInterni($html);
        $html = $this->decodifica($html);

        return trim($this->normalizzaGliSpazi($html));
    }

    /**
     * Un titolo: entita' decodificate e nessun tag.
     */
    public function titolo(string $titolo): string
    {
        return trim(strip_tags($this->decodifica($titolo)));
    }

    /**
     * Il sommario, con il contenuto come ripiego quando WordPress non ne ha uno
     * degno: alcuni comunicati hanno l'estratto vuoto o ridotto a tre parole, e
     * il sommario e' quello che il feed RSS e le anteprime social mostrano.
     */
    public function sommario(string $estratto, ?string $contenuto = null): string
    {
        $estratto = preg_replace('/<!--.*?-->/s', '', $estratto) ?? $estratto;

        // WordPress chiude l'estratto troncato con `[…]` in una delle sue
        // codifiche: diventa un carattere solo.
        $estratto = str_replace(['[&hellip;]', '[&#8230;]', "[\u{2026}]"], '…', $estratto);
        $estratto = trim(strip_tags($this->decodifica($estratto)));

        if (mb_strlen($estratto) < 50 && $contenuto !== null && $contenuto !== '') {
            $pulito = strip_tags($this->decodifica($contenuto));
            $estratto = trim(preg_replace('/\s+/', ' ', $pulito) ?? $pulito);
        }

        return $this->tronca($estratto);
    }

    /**
     * Se lo slug di WordPress si puo' tenere.
     *
     * Quando in redazione si pubblica senza titolo, o lo si cambia dopo,
     * WordPress lascia lo slug che si e' generato da solo: `41541-2`, cioe'
     * l'id del post. Finirebbe tale e quale nell'indirizzo della notizia e nel
     * feed che legge la Lega.
     */
    public function slugValido(string $slug): bool
    {
        if (preg_match('/^\d+(-\d+)?$/', $slug) === 1) {
            return false;
        }

        return mb_strlen($slug) >= 5;
    }

    /**
     * Lo slug ricavato dal titolo, per i casi sopra.
     */
    public function slugDalTitolo(string $titolo): string
    {
        $slug = mb_strtolower($titolo);
        $slug = strtr($slug, [
            'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i',
            'ò' => 'o', 'ù' => 'u', 'ć' => 'c', 'č' => 'c',
            'ž' => 'z', 'š' => 's', 'đ' => 'dj', 'ñ' => 'n',
        ]);
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug, '-');

        if (mb_strlen($slug) > 80) {
            $slug = rtrim(mb_substr($slug, 0, 80), '-');
        }

        return $slug;
    }

    /**
     * Tronca alla fine di una frase, o all'ultima parola intera.
     */
    private function tronca(string $testo): string
    {
        if (mb_strlen($testo) <= 280) {
            return trim($testo);
        }

        $tagliato = mb_substr($testo, 0, 280);

        $fineFrase = max(
            (int) mb_strrpos($tagliato, '. '),
            (int) mb_strrpos($tagliato, '! '),
            (int) mb_strrpos($tagliato, '? '),
            (int) mb_strrpos($tagliato, ".\n"),
        );

        if ($fineFrase > 100) {
            return trim(mb_substr($testo, 0, $fineFrase + 1));
        }

        $ultimoSpazio = mb_strrpos($tagliato, ' ');

        return trim(mb_substr($testo, 0, $ultimoSpazio === false ? 250 : $ultimoSpazio).'…');
    }

    /** I commenti di Gutenberg e dei plugin di condivisione. */
    private function togliICommenti(string $html): string
    {
        return preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
    }

    /**
     * I `<span>` che servono solo a vestire il testo, tenendone il contenuto.
     *
     * Si ripete finche' non cambia piu' niente, perche' l'editor li annida:
     * un colore dentro un carattere dentro un corpo. Il tetto ai giri e' la
     * rete di sicurezza contro un HTML malformato che non converge.
     */
    private function togliGliSpanDiFormattazione(string $html): string
    {
        $html = $this->ripeti(
            $html,
            10,
            fn (string $testo): string => preg_replace(
                '/<span\s+style="[^"]*(?:font-family|color|font-size|font-weight|font-style)[^"]*"\s*>(.*?)<\/span>/is',
                '$1',
                $testo
            ) ?? $testo
        );

        return $this->ripeti(
            $html,
            5,
            fn (string $testo): string => preg_replace('/<span\s*>(.*?)<\/span>/is', '$1', $testo) ?? $testo
        );
    }

    /**
     * @param  callable(string): string  $passata
     */
    private function ripeti(string $html, int $giriMassimi, callable $passata): string
    {
        $precedente = '';

        while ($html !== $precedente && $giriMassimi-- > 0) {
            $precedente = $html;
            $html = $passata($html);
        }

        return $html;
    }

    private function togliGliAllineamenti(string $html): string
    {
        return preg_replace('/\s+align="[^"]*"/i', '', $html) ?? $html;
    }

    private function togliGliStiliInLinea(string $html): string
    {
        return preg_replace('/\s+style="[^"]*"/i', '', $html) ?? $html;
    }

    /**
     * Le classi `wp-*`, `is-*` e `has-*`: sono i nomi con cui il tema di
     * WordPress le colorava, e da noi non corrispondono a niente. Se l'attributo
     * resta vuoto se ne va del tutto.
     */
    private function togliLeClassiDiWordPress(string $html): string
    {
        return preg_replace_callback(
            '/\s+class="([^"]*)"/i',
            function (array $pezzi): string {
                $classi = array_filter(
                    preg_split('/\s+/', $pezzi[1]) ?: [],
                    fn (string $classe): bool => $classe !== '' && preg_match('/^(wp-|is-|has-)/', $classe) !== 1
                );

                return $classi === [] ? '' : ' class="'.implode(' ', $classi).'"';
            },
            $html
        ) ?? $html;
    }

    /**
     * I link ad altre pagine del vecchio sito.
     *
     * Quelli a `wp-content` non si toccano: sono file, e li porta sul nostro
     * disco `MediaDelVecchioSito`.
     */
    private function riscriviILinkInterni(string $html): string
    {
        $dominio = preg_quote(self::DOMINIO, '/');

        return preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']https?:\/\/(?:www\.)?'.$dominio.'\/([^"\']*?)["\']([^>]*?)>(.*?)<\/a>/is',
            function (array $pezzi): string {
                [$intero, $prima, $percorso, $dopo, $testo] = $pezzi;
                $percorso = trim($percorso, '/');

                if (str_contains($percorso, 'wp-content/')) {
                    return $intero;
                }

                if (isset($this->slugConosciuti[$percorso])) {
                    return '<a '.$prima.'href="/news/'.$percorso.'"'.$dopo.'>'.$testo.'</a>';
                }

                return $testo;
            },
            $html
        ) ?? $html;
    }

    private function decodifica(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Paragrafi e grassetti rimasti vuoti dopo le potature qui sopra. */
    private function normalizzaGliSpazi(string $html): string
    {
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html) ?? $html;
        $html = preg_replace('/<(b|strong)>\s*<\/\1>/i', '', $html) ?? $html;

        return preg_replace('/(\s*\n){3,}/', "\n\n", $html) ?? $html;
    }
}
