<?php

namespace Database\Seeders;

use App\Enums\AuctionStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\Auction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * I dati con cui la scansione di accessibilità percorre carrello, checkout e
 * checkout di un'asta sull'app locale (`scansione-accessibilita.yml`, lavoro
 * `flussi`).
 *
 * Quei passaggi non si possono scansionare sul sito vero: servono un
 * articolo nel carrello, un ordine inviato e un'asta vinta, cioè scritture in
 * produzione. Qui nascono un prodotto a taglie, un vincitore d'asta e le zone
 * di spedizione, e la scansione arriva fino alla pagina di conferma con un
 * ordine a bonifico sul database usa e getta della CI.
 *
 * Si rifiuta di girare in produzione. Le credenziali sono di prova, valide
 * solo su quel database; la password si può cambiare con SCANSIONE_PASSWORD,
 * letta anche dallo script.
 */
class ScansioneAccessibilitaSeeder extends Seeder
{
    public const EMAIL = 'scansione-accessibilita@example.test';

    public const PASSWORD_DI_PROVA = 'Scansione-Accessibilita-2026!';

    public const PRODOTTO = 'maglia-scansione-accessibilita';

    /** Uuid fisso: la scansione apre `/shop/checkout/asta/{token}`. */
    public const TOKEN_ASTA = '00000000-0000-4000-8000-000000000a11';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ScansioneAccessibilitaSeeder crea utenti e aste di prova: non gira in produzione.');
        }

        $this->call([ShippingZonesSeeder::class, ShopSettingsSeeder::class]);

        $prodotto = Product::withTrashed()->updateOrCreate(['slug' => self::PRODOTTO], [
            'name' => ['it' => 'Maglia di prova', 'en' => 'Test jersey'],
            'description' => ['it' => 'Prodotto per la scansione di accessibilità.', 'en' => 'Accessibility scan product.'],
            'price' => 59.00,
            'stock' => 30,
            'sku' => 'A11Y-001',
            'weight' => 0.3,
            'is_active' => true,
            'type' => ProductType::Variable,
            'deleted_at' => null,
        ]);

        foreach (['S', 'M', 'L'] as $taglia) {
            ProductVariant::updateOrCreate(
                ['product_id' => $prodotto->id, 'size' => $taglia],
                ['sku' => 'A11Y-001-'.$taglia, 'stock' => 10, 'price_modifier' => 0],
            );
        }

        $vincitore = User::updateOrCreate(['email' => self::EMAIL], [
            'name' => 'Scansione Accessibilita',
            'password' => Hash::make((string) (getenv('SCANSIONE_PASSWORD') ?: self::PASSWORD_DI_PROVA)),
            'email_verified_at' => now(),
        ]);
        $vincitore->forceFill(['role' => UserRole::Customer, 'is_active' => true])->save();

        $pezzoAllAsta = Product::withTrashed()->updateOrCreate(['slug' => self::PRODOTTO.'-asta'], [
            'name' => ['it' => 'Maglia autografata di prova', 'en' => 'Signed test jersey'],
            'price' => 100.00,
            'stock' => 1,
            'sku' => 'A11Y-ASTA',
            'is_active' => true,
            'type' => ProductType::Auction,
            'deleted_at' => null,
        ]);

        $asta = Auction::withTrashed()->firstOrNew(['product_id' => $pezzoAllAsta->id]);
        $asta->fill([
            'title' => ['it' => 'Asta di prova', 'en' => 'Test auction'],
            'description' => ['it' => 'Asta per la scansione di accessibilità.', 'en' => 'Accessibility scan auction.'],
            'size' => 'M',
            'starting_price' => 50,
            'current_bid' => 120,
            'bid_increment' => 5,
            'max_bid_jump' => 100,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDay(),
        ]);
        // Campi fuori da $fillable (Auction): si scrivono a mano, come fa il
        // servizio delle aste quando chiude un'asta.
        $asta->forceFill([
            'status' => AuctionStatus::Ended,
            'winner_user_id' => $vincitore->id,
            'winner_checkout_token' => self::TOKEN_ASTA,
            'winner_checkout_deadline' => now()->addDays(3),
            'deleted_at' => null,
        ])->save();

        $this->command->info('Dati per la scansione di accessibilità pronti.');
    }
}
