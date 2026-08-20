<?php

namespace Tests\Feature\Wikipedia;

use App\Services\Wikipedia\PalmaresParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il parser gira su wikitesto reale, salvato in tests/Fixtures/Wikipedia.
 *
 * Come per le fixture della Lega: se Wikipedia cambia i template questi test
 * falliscono solo dopo che le fixture sono state riscaricate. Servono a
 * bloccare le regressioni nostre, non a sorvegliare il sito remoto.
 */
class PalmaresParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/Wikipedia/{$name}.wikitext"));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(string $name): array
    {
        return (new PalmaresParser)->parse($this->fixture($name));
    }

    /**
     * @param  list<array<string, mixed>>  $honours
     * @return list<array<string, mixed>>
     */
    private function only(array $honours, string $category): array
    {
        return array_values(array_filter($honours, fn (array $h): bool => $h['category'] === $category));
    }

    #[Test]
    public function legge_i_titoli_di_club_una_riga_per_edizione(): void
    {
        $club = $this->only($this->parse('ognjenovic'), 'club');

        // Il secondo parametro di {{Pallavolopalm}} non è il numero di titoli:
        // il conteggio deve venire dagli anni elencati nella riga successiva.
        // "Campionato polacco|1" ha due edizioni e vale due righe.
        $polacco = array_values(array_filter($club, fn (array $h): bool => $h['competition'] === 'Campionato polacco'));

        $this->assertCount(2, $polacco);
        $this->assertSame(['2013-14', '2014-15'], array_column($polacco, 'edition'));
        $this->assertSame([2013, 2014], array_column($polacco, 'year'));
        $this->assertNull($polacco[0]['medal']);
    }

    #[Test]
    public function toglie_il_suffisso_di_genere_dal_nome_della_competizione(): void
    {
        $club = $this->only($this->parse('ognjenovic'), 'club');
        $competitions = array_column($club, 'competition');

        $this->assertContains('Coppa CEV', $competitions);
        $this->assertNotContains('Coppa CEV femminile', $competitions);
    }

    #[Test]
    public function legge_le_medaglie_della_nazionale_maggiore_dallinfobox(): void
    {
        $national = $this->only($this->parse('ognjenovic'), 'national');

        $olimpiadi = array_values(array_filter($national, fn (array $h): bool => $h['competition'] === 'Giochi olimpici'));

        $this->assertCount(2, $olimpiadi);
        $this->assertSame(['silver', 'bronze'], array_column($olimpiadi, 'medal'));
        $this->assertSame('Rio de Janeiro 2016', $olimpiadi[0]['edition']);
        $this->assertSame(2016, $olimpiadi[0]['year']);
    }

    #[Test]
    public function riconosce_entrambe_le_notazioni_di_medaglia(): void
    {
        // Ognjenović usa {{simbolo|Gold medal europe.svg}}, Armini {{Med|O|Mondo}}:
        // sono la stessa cosa scritta in due epoche diverse di Wikipedia.
        $conSimbolo = $this->only($this->parse('ognjenovic'), 'national');
        $europeanLeague = array_values(array_filter($conSimbolo, fn (array $h): bool => $h['competition'] === 'European League'));

        $this->assertNotEmpty($europeanLeague);
        $this->assertSame('gold', $europeanLeague[0]['medal']);

        $conMed = $this->only($this->parse('armini'), 'national');

        $this->assertNotEmpty($conMed);
        $this->assertSame(
            ['silver', 'gold', 'gold', 'gold'],
            array_column($conMed, 'medal'),
        );
    }

    #[Test]
    public function separa_competizione_ed_edizione_nelle_medaglie_di_sezione(): void
    {
        $national = $this->only($this->parse('armini'), 'national');

        $this->assertSame('Campionato mondiale under 18', $national[0]['competition']);
        $this->assertSame('2019', $national[0]['edition']);
        $this->assertSame(2019, $national[0]['year']);
    }

    #[Test]
    public function legge_i_premi_individuali_con_anno_competizione_e_riconoscimento(): void
    {
        $awards = $this->only($this->parse('armini'), 'individual');

        $this->assertCount(1, $awards);
        $this->assertSame('Campionato mondiale under 20', $awards[0]['competition']);
        $this->assertSame('Miglior libero', $awards[0]['note']);
        $this->assertSame(2021, $awards[0]['year']);
        $this->assertNull($awards[0]['medal']);
    }

    #[Test]
    public function una_voce_senza_titoli_di_club_non_ne_inventa(): void
    {
        // Armini ha solo medaglie giovanili e un premio: la sezione Club non
        // esiste proprio nella voce.
        $this->assertSame([], $this->only($this->parse('armini'), 'club'));
    }

    #[Test]
    public function non_ripete_lo_stesso_trofeo_letto_da_due_punti_della_voce(): void
    {
        $honours = $this->parse('bosetti');

        $keys = array_map(
            fn (array $h): string => $h['category'].'|'.$h['competition'].'|'.($h['edition'] ?? '').'|'.($h['note'] ?? ''),
            $honours,
        );

        $this->assertSame(array_unique($keys), $keys);
    }

    #[Test]
    public function un_wikitesto_senza_palmares_restituisce_un_elenco_vuoto(): void
    {
        $this->assertSame([], (new PalmaresParser)->parse("== Biografia ==\nTesto qualunque.\n"));
    }
}
