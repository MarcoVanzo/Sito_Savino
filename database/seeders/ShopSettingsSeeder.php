<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Seed delle impostazioni e-commerce per SiteSetting.
 *
 * Crea le chiavi di configurazione dello shop e delle aste con i valori di
 * default. Ogni chiave è idempotente: viene creata solo se non esiste già
 * (firstOrCreate).
 *
 * L'elenco sta in `database/data/impostazioni_shop.php`, perché lo legge anche
 * la pagina "Impostazioni Shop & Aste" del pannello: una chiave che non è in
 * archivio deve comunque aprirsi con il suo valore di partenza.
 */
class ShopSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SiteSetting::definizioniDelloShop() as $setting) {
            SiteSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }

        $this->command?->info('✅ Impostazioni shop e aste create/aggiornate.');
    }
}
