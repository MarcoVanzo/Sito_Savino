<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Privacy Policy e Cookie Policy: un posto solo da cui leggerle.
 *
 * I testi stanno in `database/data/informative_privacy.php`. Li usano il
 * seeder delle pagine, perché un ambiente nuovo deve nascere con l'informativa
 * di oggi, e le migrazioni che riscrivono a guardia quella pubblicata in
 * produzione. Finché il seeder teneva la sua copia, ogni database nuovo — e
 * quello dei test — nasceva con il testo del 2025, che diceva che il sito
 * raccoglie "esclusivamente dati tecnici" e riportava un indirizzo sbagliato.
 */
class TestiDelleInformative
{
    /**
     * @return array<string, array{firme: list<string>, contenuto: array<string, string>}>
     */
    public static function tutte(): array
    {
        return require database_path('data/informative_privacy.php');
    }

    /**
     * Il testo per lingua, già ripulito e pronto da salvare.
     *
     * @return array<string, string>
     */
    public static function contenuto(string $slug): array
    {
        $informativa = self::tutte()[$slug] ?? null;

        if ($informativa === null) {
            return [];
        }

        return array_map(self::ripulisci(...), $informativa['contenuto']);
    }

    /**
     * Riscrive le informative pubblicate, lasciando stare quelle che la
     * redazione ha già messo mano.
     *
     * È il corpo di ogni migrazione che pubblica una revisione: cercare la
     * pagina, riconoscerne una firma, sostituire tutte le lingue. Scritto una
     * volta qui, perché la terza copia dello stesso `foreach` è il momento in
     * cui le copie cominciano a divergere.
     *
     * @return list<string> gli slug effettivamente riscritti
     */
    public static function riscriviDoveNonToccata(): array
    {
        $riscritte = [];

        foreach (self::tutte() as $slug => $informativa) {
            $pagina = DB::table('pages')->where('slug', $slug)->first();

            if (! $pagina) {
                continue;
            }

            $contenuti = json_decode((string) $pagina->content, true);

            if (! is_array($contenuti) || ! self::eAncoraUnTestoPrecedente($contenuti, $informativa['firme'])) {
                continue;
            }

            foreach (self::contenuto($slug) as $lingua => $testo) {
                $contenuti[$lingua] = $testo;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                'content' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            $riscritte[] = $slug;
        }

        return $riscritte;
    }

    /**
     * Una pagina già riscritta dalla redazione non porta più nessuna delle
     * firme delle versioni precedenti: in quel caso non si tocca.
     *
     * @param  array<string, string>  $contenuti
     * @param  list<string>  $firme
     */
    public static function eAncoraUnTestoPrecedente(array $contenuti, array $firme): bool
    {
        $tutto = implode(' ', array_map('strval', $contenuti));

        foreach ($firme as $firma) {
            if (str_contains($tutto, $firma)) {
                return true;
            }
        }

        return false;
    }

    /**
     * L'heredoc del file dati è indentato per leggibilità: l'indentazione non
     * deve finire nell'HTML, dove l'editor del pannello la mostrerebbe come
     * spazi veri.
     */
    private static function ripulisci(string $testo): string
    {
        return trim(preg_replace('/^[ \t]+/m', '', $testo) ?? $testo);
    }
}
