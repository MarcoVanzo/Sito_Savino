<?php

namespace Tests\Feature\Social;

use App\Enums\UserRole;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\MetaOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La callback OAuth è l'unico punto del modulo raggiungibile dall'esterno con
 * una richiesta costruita a mano: lo state è ciò che impedisce a chiunque di
 * far collegare al pannello un account Meta non nostro.
 *
 * L'altro comportamento che vale la pena fissare è che ricollegare aggiorni la
 * riga esistente: duplicarla scollegherebbe la serie storica già scaricata.
 */
class MetaOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meta.app_id', '1234567890');
        config()->set('services.meta.app_secret', 'segreto-di-prova');
    }

    #[Test]
    public function collega_tutte_le_pagine_amministrate_dal_profilo(): void
    {
        $user = $this->redattore();
        $state = app(MetaOAuthService::class)->createState($user->id);

        $this->fakeGraph();

        $this->actingAs($user)
            ->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => $state]))
            ->assertRedirect();

        // Prima squadra e settore giovanile stanno su portfolio diversi ma sotto
        // lo stesso profilo: un collegamento solo deve portarli dentro entrambi.
        $this->assertDatabaseCount('social_accounts', 2);

        $prima = SocialAccount::query()->where('page_id', '366987790098538')->firstOrFail();
        $this->assertSame('savinodelbenevolley', $prima->ig_username);
        $this->assertTrue($prima->isConnected());

        $youth = SocialAccount::query()->where('page_id', '113923971729492')->firstOrFail();
        // La Pagina senza Instagram si collega comunque: le metriche Facebook ci sono.
        $this->assertFalse($youth->hasInstagram());
    }

    #[Test]
    public function il_token_non_finisce_in_chiaro_nel_database(): void
    {
        $user = $this->redattore();
        $state = app(MetaOAuthService::class)->createState($user->id);

        $this->fakeGraph();

        $this->actingAs($user)->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => $state]));

        $stored = DB::table('social_accounts')->where('page_id', '366987790098538')->value('access_token');

        $this->assertNotSame('token-pagina-prima-squadra', $stored);
        $this->assertSame(
            'token-pagina-prima-squadra',
            SocialAccount::query()->where('page_id', '366987790098538')->value('access_token'),
        );
    }

    #[Test]
    public function ricollegare_aggiorna_la_riga_invece_di_duplicarla(): void
    {
        $user = $this->redattore();

        $esistente = SocialAccount::factory()->create([
            'name' => 'Prima squadra',
            'page_id' => '366987790098538',
            'access_token' => 'token-vecchio',
        ]);

        $this->fakeGraph();

        $state = app(MetaOAuthService::class)->createState($user->id);
        $this->actingAs($user)->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => $state]));

        $this->assertSame(1, SocialAccount::query()->where('page_id', '366987790098538')->count());

        $esistente->refresh();
        $this->assertSame('token-pagina-prima-squadra', $esistente->access_token);
        // L'etichetta è redazionale: il nome della Pagina su Meta cambia, questa no.
        $this->assertSame('Prima squadra', $esistente->name);
    }

    #[Test]
    public function uno_state_sconosciuto_non_collega_niente(): void
    {
        $user = $this->redattore();

        Http::fake();

        $this->actingAs($user)
            ->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => 'inventato']))
            ->assertRedirect();

        $this->assertDatabaseCount('social_accounts', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function uno_state_scaduto_non_collega_niente(): void
    {
        $user = $this->redattore();
        $state = app(MetaOAuthService::class)->createState($user->id);

        DB::table('social_oauth_states')->where('token', $state)->update(['expires_at' => now()->subMinute()]);

        Http::fake();

        $this->actingAs($user)
            ->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => $state]))
            ->assertRedirect();

        $this->assertDatabaseCount('social_accounts', 0);
    }

    #[Test]
    public function lo_state_vale_una_volta_sola(): void
    {
        $user = $this->redattore();
        $state = app(MetaOAuthService::class)->createState($user->id);

        $this->fakeGraph();

        $this->actingAs($user)->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => $state]));
        SocialAccount::query()->delete();

        // Riusare lo stesso state deve fallire: altrimenti un URL finito in un log
        // o nella cronologia resterebbe riutilizzabile.
        $this->actingAs($user)->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => $state]));

        $this->assertDatabaseCount('social_accounts', 0);
    }

    #[Test]
    public function chi_non_gestisce_la_comunicazione_non_puo_avviare_il_collegamento(): void
    {
        $user = $this->utenteConRuolo(UserRole::ShopManager);

        $this->actingAs($user)->get(route('admin.social.meta.connect'))->assertForbidden();
    }

    /**
     * La callback è una rotta come le altre: il ruolo va chiesto anche lì,
     * altrimenti basta un account del negozio per chiuderci sopra un giro OAuth.
     */
    #[Test]
    public function chi_non_gestisce_la_comunicazione_non_puo_chiudere_il_collegamento(): void
    {
        $state = app(MetaOAuthService::class)->createState($this->redattore()->id);

        $this->fakeGraph();

        $this->actingAs($this->utenteConRuolo(UserRole::ShopManager))
            ->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => $state]))
            ->assertForbidden();

        $this->assertDatabaseCount('social_accounts', 0);
    }

    #[Test]
    public function il_collegamento_si_chiude_solo_su_chi_lo_ha_aperto(): void
    {
        $state = app(MetaOAuthService::class)->createState($this->redattore()->id);

        $this->fakeGraph();

        $this->actingAs($this->redattore())
            ->get(route('admin.social.meta.callback', ['code' => 'codice-di-prova', 'state' => $state]))
            ->assertRedirect();

        $this->assertDatabaseCount('social_accounts', 0);
    }

    private function redattore(): User
    {
        return $this->utenteConRuolo(UserRole::CommunicationManager);
    }

    /**
     * `role` e `must_change_password` non sono assegnabili in massa (lo dice il
     * model): passarli alla factory non ha alcun effetto e il test finirebbe per
     * verificare i permessi di un utente senza ruolo, cioè niente.
     */
    private function utenteConRuolo(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'must_change_password' => false])->save();

        return $user->refresh();
    }

    private function fakeGraph(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'oauth/access_token')) {
                return Http::response(['access_token' => 'token-utente', 'expires_in' => 5184000], 200);
            }

            return Http::response([
                'data' => [
                    [
                        'id' => '366987790098538',
                        'name' => 'Savino Del Bene Volley Scandicci',
                        'access_token' => 'token-pagina-prima-squadra',
                        'instagram_business_account' => ['id' => '17841400000000000', 'username' => 'savinodelbenevolley'],
                    ],
                    [
                        'id' => '113923971729492',
                        'name' => 'SDB Volley Youth',
                        'access_token' => 'token-pagina-youth',
                    ],
                ],
            ], 200);
        });
    }
}
