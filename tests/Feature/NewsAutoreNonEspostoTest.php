<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le pagine delle notizie sono pubbliche e finiscono in cache: dell'autore
 * devono portare solo il nome. Prima il props conteneva l'intero User
 * (email, telefono, indirizzo, id cliente Stripe, ruolo).
 */
class NewsAutoreNonEspostoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function autore(): User
    {
        return User::factory()->create([
            'name' => 'Redattrice',
            'email' => 'redattrice@example.test',
            'phone' => '+39 333 0000000',
            'address' => 'Via Segreta 1',
        ]);
    }

    #[Test]
    public function la_lista_delle_notizie_espone_dell_autore_solo_il_nome(): void
    {
        Post::factory()->create(['author_id' => $this->autore()->id]);

        $response = $this->get('/news')->assertOk();

        $response->assertInertia(fn ($page) => $page->component('Public/News')
            ->where('posts.data.0.author', ['name' => 'Redattrice'])
        );
        $this->assertStringNotContainsString('redattrice@example.test', $response->getContent());
        $this->assertStringNotContainsString('333 0000000', $response->getContent());
    }

    #[Test]
    public function il_dettaglio_e_le_correlate_espongono_dell_autore_solo_il_nome(): void
    {
        $autore = $this->autore();
        $categoria = Category::factory()->create();
        Post::factory()->create(['slug' => 'notizia', 'author_id' => $autore->id])->categories()->attach($categoria);
        Post::factory()->create(['author_id' => $autore->id])->categories()->attach($categoria);

        $response = $this->get('/news/notizia')->assertOk();

        $response->assertInertia(fn ($page) => $page->component('Public/NewsDetail')
            ->where('post.author', ['name' => 'Redattrice'])
            ->missing('relatedPosts.0.author')
        );
        $this->assertStringNotContainsString('redattrice@example.test', $response->getContent());
    }

    /**
     * In produzione le chiavi `public:news:*` contengono già la copia con
     * l'utente intero: non deve arrivare al browser nemmeno quella.
     */
    #[Test]
    public function una_copia_vecchia_in_cache_viene_ripulita_prima_di_uscire(): void
    {
        $vecchioAutore = [
            'id' => 1, 'name' => 'Redattrice', 'email' => 'redattrice@example.test',
            'phone' => '+39 333 0000000', 'stripe_customer_id' => 'cus_x', 'role' => 'super_admin',
        ];
        Cache::put('public:news:it:vecchia', [
            'post' => ['slug' => 'vecchia', 'title' => 'Vecchia', 'author_id' => 1, 'author' => $vecchioAutore],
            'relatedPosts' => [['slug' => 'altra', 'author' => $vecchioAutore]],
        ], now()->addMinutes(10));
        Cache::put('public:news:it:cat:all:page:1', [
            'data' => [['slug' => 'vecchia', 'author' => $vecchioAutore]],
            'current_page' => 1, 'last_page' => 1, 'links' => [], 'total' => 1, 'per_page' => 12,
        ], now()->addMinutes(5));

        $dettaglio = $this->get('/news/vecchia')->assertOk();
        $dettaglio->assertInertia(fn ($page) => $page
            ->where('post.author', ['name' => 'Redattrice'])
            ->where('relatedPosts.0.author', ['name' => 'Redattrice'])
        );
        $this->assertStringNotContainsString('redattrice@example.test', $dettaglio->getContent());

        $lista = $this->get('/news')->assertOk();
        $lista->assertInertia(fn ($page) => $page->where('posts.data.0.author', ['name' => 'Redattrice']));
        $this->assertStringNotContainsString('cus_x', $lista->getContent());
    }
}
