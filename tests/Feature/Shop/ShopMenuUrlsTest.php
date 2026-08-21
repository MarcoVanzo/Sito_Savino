<?php

namespace Tests\Feature\Shop;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ShopMenuUrlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_italian_category_urls_return_200(): void
    {
        $response = $this->get('/shop/categoria/kit-gara-25-26');
        $response->assertStatus(200);

        $response2 = $this->get('/shop/categoria/abbigliamento');
        $response2->assertStatus(200);
    }

    public function test_english_category_urls_return_200(): void
    {
        $response = $this->get('/en/shop/category/kit-gara-25-26');
        $response->assertStatus(200);

        $response2 = $this->get('/en/shop/category/abbigliamento');
        $response2->assertStatus(200);
    }

    public function test_incorrect_english_menu_url_returns_404(): void
    {
        // Se il menu genera '/en/shop/categoria/...' invece di '/en/shop/category/...', deve dare 404
        $response = $this->get('/en/shop/categoria/kit-gara-25-26');
        $response->assertStatus(404);
    }

    /**
     * "kit-gara" e' il reparto che raccoglie Home, Away e Champions: prima la
     * scorciatoia portava alla sola linea Home e non si poteva scegliere.
     */
    public function test_legacy_italian_redirects_work(): void
    {
        $response = $this->get('/shop/kit-gara');
        $response->assertRedirect('/shop/categoria/kit-gara');
        $response->assertStatus(301);

        $response2 = $this->get('/shop/abbigliamento');
        $response2->assertRedirect('/shop/categoria/abbigliamento');
        $response2->assertStatus(301);
    }

    public function test_legacy_english_redirects_work(): void
    {
        $response = $this->get('/en/shop/kit-gara');
        $response->assertRedirect('/en/shop/category/kit-gara');
        $response->assertStatus(301);

        $response2 = $this->get('/en/shop/abbigliamento');
        $response2->assertRedirect('/en/shop/category/abbigliamento');
        $response2->assertStatus(301);
    }

    public function test_menu_item_url_localization_in_get_tree(): void
    {
        // Pulisci cache prima di iniziare
        MenuItem::clearCache();

        // Creamo una voce di menu finta
        $parent = MenuItem::create([
            'location' => 'main',
            'label' => ['it' => 'Shop Test', 'en' => 'Shop Test'],
            'url' => '/shop',
            'is_active' => true,
            'sort_order' => 999, // molto alto per non rompere
        ]);

        $child = MenuItem::create([
            'location' => 'main',
            'parent_id' => $parent->id,
            'label' => ['it' => 'Kit Gara Test', 'en' => 'Match Kit Test'],
            'url' => '/shop/categoria/kit-gara-25-26',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // 1. Testa in Italiano
        app()->setLocale('it');
        MenuItem::clearCache();
        $treeIt = MenuItem::getTree('main');

        $parentIt = collect($treeIt)->firstWhere('id', $parent->id);
        $this->assertNotNull($parentIt);
        $this->assertEquals('/shop', $parentIt['href']);
        $this->assertEquals('/shop/categoria/kit-gara-25-26', $parentIt['children'][0]['href']);

        // 2. Testa in Inglese
        app()->setLocale('en');
        MenuItem::clearCache();
        $treeEn = MenuItem::getTree('main');

        $parentEn = collect($treeEn)->firstWhere('id', $parent->id);
        $this->assertNotNull($parentEn);
        $this->assertEquals('/en/shop', $parentEn['href']);
        // Deve essere convertito in /en/shop/category/...
        $this->assertEquals('/en/shop/category/kit-gara-25-26', $parentEn['children'][0]['href']);
    }

    public function test_laravel_route_matching_experiment(): void
    {
        $url = '/shop/categoria/kit-gara-25-26';

        $request = Request::create($url, 'GET');

        try {
            $route = app('router')->getRoutes()->match($request);
            $routeName = $route->getName();
            $parameters = $route->parameters();

            $this->assertEquals('shop.category', $routeName);
            $this->assertArrayHasKey('category', $parameters);

            // Proviamo a rigenerare l'URL in inglese
            $targetRouteName = 'en.'.$routeName;
            $newUrl = route($targetRouteName, $parameters, false);

            $this->assertEquals('/en/shop/category/kit-gara-25-26', $newUrl);
        } catch (NotFoundHttpException $e) {
            $this->fail('La rotta non è stata trovata dal router di Laravel.');
        }
    }

    public function test_menu_item_url_localization_preserves_query_and_fragments(): void
    {
        // 1. URL con query string
        $urlWithQuery = '/shop/categoria/kit-gara-25-26?q=search&size=M';
        $localizedQuery = MenuItem::localizeUrl($urlWithQuery, 'en');
        $this->assertEquals('/en/shop/category/kit-gara-25-26?q=search&size=M', $localizedQuery);

        // 2. URL con frammento/ancora
        $urlWithFragment = '/shop/categoria/abbigliamento#guida';
        $localizedFragment = MenuItem::localizeUrl($urlWithFragment, 'en');
        $this->assertEquals('/en/shop/category/abbigliamento#guida', $localizedFragment);

        // 3. URL con entrambi
        $urlBoth = '/shop/categoria/kit-gara-25-26?color=blue#tab-size';
        $localizedBoth = MenuItem::localizeUrl($urlBoth, 'en');
        $this->assertEquals('/en/shop/category/kit-gara-25-26?color=blue#tab-size', $localizedBoth);

        // 4. URL esterno (non deve essere alterato)
        $urlExternal = 'https://errea.com/size-guide?lang=it#table';
        $localizedExternal = MenuItem::localizeUrl($urlExternal, 'en');
        $this->assertEquals('https://errea.com/size-guide?lang=it#table', $localizedExternal);
    }
}
