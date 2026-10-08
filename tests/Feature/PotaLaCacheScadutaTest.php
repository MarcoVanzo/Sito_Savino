<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PotaLaCacheScadutaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function toglie_solo_le_righe_scadute_della_cache_su_database(): void
    {
        config(['cache.default' => 'database']);
        $prefisso = Cache::store('database')->getPrefix();

        Cache::store('database')->put('viva', 'si', 600);
        Cache::store('database')->forever('per-sempre', 'si');
        DB::table('cache')->insert([
            ['key' => $prefisso.'scaduta', 'value' => serialize('no'), 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => $prefisso.'scaduta-ora', 'value' => serialize('no'), 'expiration' => now()->getTimestamp()],
        ]);

        $this->artisan('cache:pota-scadute')
            ->expectsOutput('Righe scadute tolte: 2.')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$prefisso.'viva', $prefisso.'per-sempre'],
            DB::table('cache')->pluck('key')->all(),
        );
    }

    #[Test]
    public function con_un_altro_driver_non_fa_niente(): void
    {
        DB::table('cache')->insert([
            'key' => 'scaduta', 'value' => serialize('no'), 'expiration' => now()->subMinute()->getTimestamp(),
        ]);

        $this->artisan('cache:pota-scadute')
            ->expectsOutput('La cache non è su database: niente da potare.')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('cache')->count());
    }
}
