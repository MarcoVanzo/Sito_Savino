<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Con la cache sul database, salvare una notizia faceva una DELETE per ogni
 * lista per categoria, pagina e lingua: più di cento query (Sentry, N+1 sul
 * modulo della notizia, 10/10/2026).
 */
class UnaNotiziaSalvataButtaLaCacheConUnaQueryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_chiavi_della_notizia_spariscono_con_una_sola_delete(): void
    {
        config(['cache.default' => 'database']);
        $categoria = Category::factory()->create();
        $notizia = Post::factory()->create();

        $daButtare = [
            'public:home:it',
            'public:news:it:'.$notizia->slug,
            'public:news:en:cat:'.$categoria->slug.':page:3',
            'public:news:it:cat:all:page:5',
            'public:news_feed:en',
        ];
        foreach ($daButtare as $chiave) {
            Cache::put($chiave, 'vecchia', 600);
        }
        Cache::put('public:sponsor:it', 'resta', 600);

        DB::enableQueryLog();
        $notizia->update(['excerpt' => 'Nuovo sommario']);
        $delete = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with($query['query'], 'delete from `cache`')
                && collect($query['bindings'])->contains(fn (string $chiave): bool => str_contains($chiave, 'public:')));
        DB::disableQueryLog();

        $this->assertCount(1, $delete);
        foreach ($daButtare as $chiave) {
            $this->assertNull(Cache::get($chiave), $chiave);
        }
        $this->assertSame('resta', Cache::get('public:sponsor:it'));
    }
}
