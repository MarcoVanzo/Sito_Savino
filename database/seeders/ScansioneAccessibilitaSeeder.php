<?php

namespace Database\Seeders;

use App\Enums\AuctionStatus;
use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Enums\ProductType;
use App\Enums\SponsorTier;
use App\Enums\TipoCreativita;
use App\Enums\UserRole;
use App\Enums\UsoCreativita;
use App\Models\Auction;
use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\Sponsor;
use App\Models\Team;
use App\Models\User;
use App\Services\Diretta\RegiaDellaDiretta;
use App\Support\Diretta\ImpostazioniDellaDiretta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * I dati con cui la scansione di accessibilità percorre carrello, checkout,
 * checkout di un'asta e diretta con sponsor sull'app locale (`scansione-accessibilita.yml`, lavoro
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

    /** L'indirizzo della diretta della gara di prova (la riconosce al secondo giro). */
    public const DIRETTA = 'https://www.youtube.com/watch?v=scansioneA11y';

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

        $this->direttaConSponsor();

        $this->command->info('Dati per la scansione di accessibilità pronti.');
    }

    /**
     * Una gara della società in corso con la diretta con sponsor:
     * uno spot prima della diretta e una cornice già mandata dalla regia, così
     * la scansione misura il pre-roll e la cornice accanto al player.
     */
    private function direttaConSponsor(): void
    {
        SiteSetting::set('diretta_sponsor_abilitata', '1');

        // La prima squadra: la diretta e il Match Day sono solo suoi, non del vivaio.
        $noi = Team::query()->where('is_internal', true)->where('category', Team::CATEGORIA_PRIMA_SQUADRA)->first()
            ?? Team::factory()->create(['is_internal' => true, 'category' => Team::CATEGORIA_PRIMA_SQUADRA]);
        $loro = Team::query()->where('is_internal', false)->first() ?? Team::factory()->create(['is_internal' => false]);
        $gara = Game::query()->updateOrCreate(['stream_url' => self::DIRETTA], [
            'season_id' => Season::query()->value('id') ?? Season::factory()->create()->id,
            'home_team_id' => $noi->id,
            'away_team_id' => $loro->id,
            // Cominciata da mezz'ora: è «oggi» per il Match Day a qualunque
            // ora gira la scansione. «Fra venti minuti» alle 23:40 era domani.
            'match_date' => now()->subMinutes(30),
            'status' => GameStatus::Scheduled,
            'competition_type' => CompetitionType::Championship,
        ]);

        $sponsor = Sponsor::query()->firstOrCreate(['name' => 'Sponsor di prova'], ['tier' => SponsorTier::Official, 'url' => 'https://www.example.com/']);
        $spot = $this->creativita($sponsor, UsoCreativita::Preroll, 'Spot di prova');
        $cornice = $this->creativita($sponsor, UsoCreativita::Cornice, 'Cornice di prova');

        $sessione = LiveSession::perLaGara($gara);
        $sessione->update(['preroll_attivo' => true, 'preroll_salta_dopo_secondi' => 2]);
        $sessione->ordina('creativitaDelPreroll', [$spot->id]);
        $sessione->ordina('creativitaDellaGara', [$cornice->id]);

        app(RegiaDellaDiretta::class)->mostra($sessione, $cornice, ImpostazioniDellaDiretta::PAUSA_DURATA_MASSIMA);
    }

    private function creativita(Sponsor $sponsor, UsoCreativita $uso, string $nome): LiveCreative
    {
        $creativita = LiveCreative::query()->updateOrCreate(['nome' => $nome], [
            'sponsor_id' => $sponsor->id,
            'uso' => $uso,
            'tipo' => TipoCreativita::Immagine,
            'durata_secondi' => 3,
            'link_url' => 'https://www.example.com/',
            'testo_alternativo' => ['it' => $nome.': marchio dello sponsor', 'en' => $nome.': sponsor logo'],
            'attiva' => true,
        ]);

        if ($creativita->getFirstMedia(LiveCreative::COLLEZIONE_CONTENUTO) === null) {
            $file = tempnam(sys_get_temp_dir(), 'a11y').'.png';
            imagepng(imagecreatetruecolor(1920, 1080), $file, 9);
            $creativita->addMedia($file)->toMediaCollection(LiveCreative::COLLEZIONE_CONTENUTO);
        }

        return $creativita;
    }
}
