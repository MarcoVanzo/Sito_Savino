<?php

namespace Tests\Feature\Wikipedia;

use App\Models\Player;
use App\Services\Wikipedia\WikipediaClient;
use App\Services\Wikipedia\WikipediaPageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le chiamate a Wikipedia sono simulate: qui si verifica la logica di
 * aggancio, non la disponibilità del sito remoto.
 */
class WikipediaPageResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): WikipediaPageResolver
    {
        return new WikipediaPageResolver(new WikipediaClient('it', 5, 'test'));
    }

    /**
     * @return array<string, mixed>
     */
    private function pageResponse(string $title, string $wikitext): array
    {
        return [
            'query' => [
                'pages' => [[
                    'pageid' => 1,
                    'title' => $title,
                    'revisions' => [[
                        'revid' => 42,
                        'slots' => ['main' => ['content' => $wikitext]],
                    ]],
                ]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function missingResponse(): array
    {
        return ['query' => ['pages' => [['title' => 'X', 'missing' => true]]]];
    }

    private function voceDiPallavolista(int $birthYear = 1994): string
    {
        return "{{Sportivo\n|Disciplina = Pallavolo\n}}\n'''Tizia''' (Milano, 2 febbraio {$birthYear}) è una pallavolista italiana.\n";
    }

    #[Test]
    public function usa_il_titolo_esatto_quando_la_voce_esiste(): void
    {
        Http::fake([
            '*' => Http::response($this->pageResponse('Caterina Bosetti', $this->voceDiPallavolista())),
        ]);

        $player = Player::factory()->create([
            'first_name' => 'Caterina',
            'last_name' => 'Bosetti',
            'date_of_birth' => '1994-02-02',
        ]);

        $page = $this->resolver()->resolve($player);

        $this->assertSame('Caterina Bosetti', $page['title']);
        $this->assertSame('sicura', $page['confidence']);
        $this->assertSame(42, $page['revid']);
    }

    #[Test]
    public function ripiega_sulla_ricerca_quando_il_titolo_esatto_non_esiste(): void
    {
        // È il caso reale di Julia Bergmann, che su Wikipedia sta all'accento:
        // senza la ricerca l'atleta resterebbe senza palmarès.
        $sequence = Http::sequence()
            ->push($this->missingResponse())
            ->push(['query' => ['search' => [['title' => 'Júlia Bergmann', 'snippet' => 'pallavolista brasiliana']]]])
            ->push($this->pageResponse('Júlia Bergmann', $this->voceDiPallavolista(2001)));

        Http::fake(['*' => $sequence]);

        $player = Player::factory()->create([
            'first_name' => 'Julia',
            'last_name' => 'Bergmann',
            'date_of_birth' => '2001-05-11',
        ]);

        $page = $this->resolver()->resolve($player);

        $this->assertSame('Júlia Bergmann', $page['title']);
    }

    #[Test]
    public function scarta_lomonima_con_lanno_di_nascita_sbagliato(): void
    {
        $sequence = Http::sequence()
            ->push($this->missingResponse())
            ->push(['query' => ['search' => [['title' => 'Tizia Caia', 'snippet' => 'pallavolista']]]])
            ->push($this->pageResponse('Tizia Caia', $this->voceDiPallavolista(1975)));

        Http::fake(['*' => $sequence]);

        $player = Player::factory()->create([
            'first_name' => 'Tizia',
            'last_name' => 'Caia',
            'date_of_birth' => '2001-05-11',
        ]);

        $this->assertNull($this->resolver()->resolve($player));
    }

    #[Test]
    public function scarta_una_voce_che_non_parla_di_pallavolo(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push($this->pageResponse('Tizia Caia', "'''Tizia Caia''' è una cestista italiana.\n"))
                ->push(['query' => ['search' => []]]),
        ]);

        $player = Player::factory()->create(['first_name' => 'Tizia', 'last_name' => 'Caia']);

        $this->assertNull($this->resolver()->resolve($player));
    }

    #[Test]
    public function laggancio_salvato_in_anagrafica_ha_la_precedenza(): void
    {
        Http::fake([
            '*' => Http::response($this->pageResponse('Immacolata Sirressi', $this->voceDiPallavolista(1990))),
        ]);

        $player = Player::factory()->create([
            'first_name' => 'Imma',
            'last_name' => 'Sirressi',
            'wikipedia_title' => 'Immacolata Sirressi',
        ]);

        $page = $this->resolver()->resolve($player);

        $this->assertSame('Immacolata Sirressi', $page['title']);
        $this->assertSame('confermata', $page['confidence']);

        // Una sola richiesta: la ricerca per nome non deve nemmeno partire.
        Http::assertSentCount(1);
    }
}
