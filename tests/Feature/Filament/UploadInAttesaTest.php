<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadInAttesaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Finche' FilePond non e' pronto il campo di upload mostra una cornice di
     * attesa: lo script che ferma il drop su quella cornice deve stare in
     * ogni pagina del pannello, o la foto trascinata apre il file nel browser.
     */
    public function test_lo_script_che_ferma_il_drop_e_nel_pannello(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        $this->actingAs($user)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('uploadInAttesaPatched', false)
            ->assertSee('.filepond--root', false);
    }

    /**
     * L'input nativo resta nascosto solo finche' FilePond non lo adotta:
     * il selettore deve escludere la classe che FilePond gli assegna.
     */
    public function test_il_tema_nasconde_solo_l_input_non_ancora_adottato(): void
    {
        $css = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString(".fi-fo-file-upload input[type='file']:not(.filepond--browser)", $css);
        $this->assertStringContainsString('.fi-fo-file-upload > div:not(:has(.filepond--root))', $css);
    }
}
