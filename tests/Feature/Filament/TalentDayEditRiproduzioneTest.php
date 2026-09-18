<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pagina Talent Day rispondeva 500 in redazione (17/09/2026).
 *
 * Il suo campo di testo "Societa' partner" si chiamava `content_data.partners`,
 * lo stesso nome che nelle Convenzioni e' l'elenco dei partner: un Repeater.
 * Filament idrata anche i campi delle sezioni nascoste, quindi quel Repeater
 * riceveva una stringa e falliva prima ancora di disegnare la pagina.
 *
 * La fixture e' il contenuto vero della pagina in produzione, con la chiave
 * vecchia: e' lo stato in cui si trova un archivio non ancora migrato.
 */
class TalentDayEditRiproduzioneTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_talent_day_si_apre_con_i_contenuti_di_produzione(): void
    {
        $pagina = $this->paginaConContenuti(
            json_decode(file_get_contents(base_path('tests/Fixtures/talentday_prod.json')), true)
        );

        Livewire::actingAs($this->redattore())
            ->test(EditPage::class, ['record' => $pagina->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('content_data.partners_note');
    }

    /**
     * La nota sulle societa' partner e' un testo: sotto il nome di un elenco
     * mandava in errore l'intera pagina.
     */
    public function test_un_testo_sotto_il_nome_di_un_elenco_non_manda_in_errore_la_pagina(): void
    {
        $pagina = $this->paginaConContenuti([
            'it' => ['partners' => 'In collaborazione con Civitavecchia Volley'],
            'en' => ['partners' => 'In partnership with Civitavecchia Volley'],
        ]);

        Livewire::actingAs($this->redattore())
            ->test(EditPage::class, ['record' => $pagina->getKey()])
            ->assertSuccessful();
    }

    /**
     * Il valore tenuto fuori dal modulo non sparisce: il salvataggio riscrive
     * solo le chiavi dei campi mostrati, e quella resta in archivio finche' la
     * migrazione non la sposta.
     */
    public function test_il_valore_tenuto_fuori_dal_modulo_resta_in_archivio(): void
    {
        $pagina = $this->paginaConContenuti([
            'it' => ['hero_label' => 'Talent Scouting', 'partners' => 'In collaborazione con Civitavecchia Volley'],
            'en' => ['hero_label' => 'Talent Scouting', 'partners' => 'In partnership with Civitavecchia Volley'],
        ]);

        Livewire::actingAs($this->redattore())
            ->test(EditPage::class, ['record' => $pagina->getKey()])
            ->set('data.content_data.hero_label', 'Talent Scouting 2027')
            ->call('save')
            ->assertHasNoErrors();

        $contenuti = $pagina->refresh()->getTranslation('content_data', 'it', false);

        $this->assertSame('In collaborazione con Civitavecchia Volley', $contenuti['partners']);
        $this->assertSame('Talent Scouting 2027', $contenuti['hero_label']);
    }

    /**
     * @param  array<string, mixed>  $contenuti
     */
    private function paginaConContenuti(array $contenuti): Page
    {
        $pagina = Page::factory()->create([
            'slug' => 'talent-day',
            'template' => 'Public/TalentDay',
            'title' => ['it' => 'Talent Day', 'en' => 'Talent Day'],
        ]);

        \DB::table('pages')->where('id', $pagina->getKey())->update([
            'content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
        ]);

        return $pagina;
    }

    private function redattore(): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $utente;
    }
}
