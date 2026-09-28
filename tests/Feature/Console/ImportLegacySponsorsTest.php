<?php

namespace Tests\Feature\Console;

use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportLegacySponsorsTest extends TestCase
{
    use RefreshDatabase;

    private function pagina(string $link): string
    {
        return '<h2>Official Supporter</h2>'
            .'<a href="'.e($link).'"><img src="https://esempio.test/logo.png" alt="My English School"></a>'
            .'<a href="https://www.altro.test/"><img src="https://esempio.test/altro.png" alt="Altro Sponsor"></a>';
    }

    public function test_i_parametri_delle_campagne_non_finiscono_nel_link(): void
    {
        $link = 'https://www.myes.school/landing/generica/?utm_source=bing&utm_medium=cpc'
            .'&utm_campaign=%5BNAZ%5D%20Sembox%20Search%20Brand&utm_term=my%20english%20school'
            .'&utm_content=Exact&sembox_source=bing_adwords_NAZ&sembox_campaign='.Str::repeat('x', 120)
            .'&msclkid=abc&sede=firenze';

        Http::fake(['*' => Http::response($this->pagina($link))]);

        $this->artisan('sponsors:import-legacy', ['--skip-logos' => true])->assertSuccessful();

        $this->assertSame(
            'https://www.myes.school/landing/generica/?sede=firenze',
            Sponsor::where('name', 'My English School')->value('url'),
        );
        $this->assertSame('https://www.altro.test/', Sponsor::where('name', 'Altro Sponsor')->value('url'));
    }

    public function test_un_link_troppo_lungo_anche_senza_campagne_perde_la_query_string(): void
    {
        $link = 'https://www.myes.school/landing/?pagina='.Str::repeat('y', 300);

        Http::fake(['*' => Http::response($this->pagina($link))]);

        $this->artisan('sponsors:import-legacy', ['--skip-logos' => true])->assertSuccessful();

        $this->assertSame('https://www.myes.school/landing/', Sponsor::where('name', 'My English School')->value('url'));
    }
}
