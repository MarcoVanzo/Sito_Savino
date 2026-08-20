<?php

namespace App\Services\Wikipedia;

use App\Enums\PlayerHonourCategory;
use App\Models\Player;
use App\Models\PlayerHonour;
use Illuminate\Support\Facades\DB;

/**
 * Scrive su `player_honours` il palmarès letto da Wikipedia.
 *
 * Due invarianti, le stesse della sincronizzazione con la Lega:
 *
 * 1. **Idempotenza.** Rilanciare non duplica: le righe di provenienza
 *    `wikipedia` vengono sostituite in blocco, non accumulate.
 * 2. **Il lavoro manuale non si tocca.** Le righe `manual` — quelle inserite o
 *    corrette in redazione — non vengono né riscritte né cancellate, e se
 *    Wikipedia ripropone lo stesso trofeo la riga importata viene scartata:
 *    altrimenti una correzione fatta a mano ricomparirebbe in doppio a ogni
 *    importazione.
 */
class PalmaresImporter
{
    public function __construct(
        private readonly PalmaresParser $parser,
        private readonly CompetitionTranslator $translator,
    ) {}

    /**
     * @return array{imported: int, kept: int, skipped: int}
     */
    public function import(Player $player, string $wikitext, string $title, int $revid, string $lang = 'it'): array
    {
        $parsed = $this->parser->parse($wikitext);

        return DB::transaction(function () use ($player, $parsed, $title, $revid, $lang): array {
            $manualKeys = $player->honours()
                ->where('source', PlayerHonour::SOURCE_MANUAL)
                ->get()
                ->map(fn (PlayerHonour $honour): string => $honour->naturalKey())
                ->all();

            $player->honours()->where('source', PlayerHonour::SOURCE_WIKIPEDIA)->delete();

            $imported = 0;
            $skipped = 0;

            foreach ($this->sort($parsed) as $index => $row) {
                $key = PlayerHonour::buildNaturalKey(
                    (string) $row['category'],
                    (string) $row['competition'],
                    $row['edition'] !== null ? (string) $row['edition'] : null,
                    $row['note'] !== null ? (string) $row['note'] : null,
                );

                if (in_array($key, $manualKeys, true)) {
                    $skipped++;

                    continue;
                }

                $player->honours()->create([
                    'category' => $row['category'],
                    'competition' => [
                        'it' => $row['competition'],
                        'en' => $this->translator->competition((string) $row['competition']),
                    ],
                    'edition' => $row['edition'],
                    'year' => $row['year'],
                    'medal' => $row['medal'],
                    'note' => $row['note'] !== null ? [
                        'it' => $row['note'],
                        'en' => $this->translator->award((string) $row['note']),
                    ] : null,
                    'source' => PlayerHonour::SOURCE_WIKIPEDIA,
                    'is_visible' => true,
                    'sort_order' => $index,
                ]);

                $imported++;
            }

            $player->forceFill([
                'wikipedia_title' => $title,
                'wikipedia_lang' => $lang,
                'wikipedia_revid' => $revid,
                'palmares_synced_at' => now(),
            ])->save();

            return [
                'imported' => $imported,
                'kept' => count($manualKeys),
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Ordine di pubblicazione: club, nazionale, premi; dentro ogni gruppo dal
     * più recente. È l'ordine in cui il banner pubblico li mostra, e viene
     * congelato in `sort_order` così la redazione può poi spostarli a mano.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sort(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $weight = PlayerHonourCategory::from((string) $a['category'])->weight()
                <=> PlayerHonourCategory::from((string) $b['category'])->weight();

            if ($weight !== 0) {
                return $weight;
            }

            return ((int) ($b['year'] ?? 0)) <=> ((int) ($a['year'] ?? 0));
        });

        return $rows;
    }
}
