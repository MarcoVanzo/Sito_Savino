<?php

namespace App\Models;

use App\Enums\HonourMedal;
use App\Enums\PlayerHonourCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * Una riga di palmarès: un trofeo di club, una medaglia in nazionale o un
 * premio individuale.
 *
 * @property PlayerHonourCategory $category
 * @property HonourMedal|null $medal
 */
class PlayerHonour extends Model
{
    use HasFactory, HasTranslations;

    /**
     * Provenienza: righe scritte dall'importazione da Wikipedia.
     * Sono le uniche che il sync può riscrivere o cancellare.
     */
    public const SOURCE_WIKIPEDIA = 'wikipedia';

    /**
     * Provenienza: righe scritte o corrette in redazione. Intoccabili dal sync.
     */
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'player_id', 'category', 'competition', 'edition', 'year',
        'medal', 'note', 'source', 'is_visible', 'sort_order',
    ];

    public $translatable = ['competition', 'note'];

    /**
     * Le stesse impostazioni predefinite della tabella, anche in memoria: una
     * riga appena costruita e non ancora riletta dal database deve già sapere
     * di essere pubblicabile, o il pannello la tratta come nascosta.
     */
    protected $attributes = [
        'source' => self::SOURCE_MANUAL,
        'is_visible' => true,
        'sort_order' => 0,
    ];

    protected $casts = [
        'category' => PlayerHonourCategory::class,
        'medal' => HonourMedal::class,
        'year' => 'integer',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * Chiave naturale della riga, usata dall'importazione per riconoscere un
     * trofeo già presente invece di duplicarlo.
     *
     * Si normalizza la competizione (accenti, maiuscole, spazi) perché il
     * wikitesto della Lega e quello di Wikipedia scrivono lo stesso torneo in
     * modi diversi da un anno all'altro.
     */
    public function naturalKey(): string
    {
        return self::buildNaturalKey(
            $this->category->value,
            (string) $this->getTranslation('competition', 'it', false),
            (string) $this->edition,
            (string) $this->getTranslation('note', 'it', false),
        );
    }

    public static function buildNaturalKey(string $category, string $competition, ?string $edition, ?string $note = null): string
    {
        return implode('|', [
            $category,
            Str::of($competition)->ascii()->lower()->squish()->toString(),
            Str::of((string) $edition)->ascii()->lower()->squish()->toString(),
            Str::of((string) $note)->ascii()->lower()->squish()->toString(),
        ]);
    }
}
