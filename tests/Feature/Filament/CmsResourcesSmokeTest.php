<?php

namespace Tests\Feature\Filament;

use App\Enums\StaffType;
use App\Enums\UserRole;
use App\Filament\Resources;
use App\Models\ContactMessage;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Game;
use App\Models\HeroSlide;
use App\Models\MenuItem;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Post;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\StaffMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test delle "tab" del pannello CMS: ogni tabella deve renderizzare
 * con almeno un record, così che le closure di colonna (badge, colori,
 * tooltip) vengano effettivamente eseguite.
 */
class CmsResourcesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => UserRole::SuperAdmin])->save();

        $this->seedContent();
    }

    private function seedContent(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $home = Team::factory()->create(['category' => 'A1']);
        $away = Team::factory()->create(['category' => 'B1']);

        Game::factory()->create([
            'season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        $player = Player::factory()->create();
        PlayerStat::factory()->create(['player_id' => $player->id, 'season_id' => $season->id]);
        Roster::factory()->create([
            'player_id' => $player->id,
            'team_id' => $home->id,
            'season_id' => $season->id,
        ]);
        Roster::factory()->create([
            'player_id' => $player->id,
            'team_id' => $away->id,
            'season_id' => $season->id,
        ]);

        Post::factory()->create();
        Page::factory()->create();
        Sponsor::factory()->create();
        HeroSlide::factory()->create();
        $event = GalleryEvent::factory()->create();
        GalleryImage::factory()->create(['gallery_event_id' => $event->id]);
        ContactMessage::factory()->create(['subject' => 'Informazioni']);
        ContactMessage::factory()->create(['subject' => 'Stampa / Media']);

        MenuItem::create([
            'label' => 'Squadra',
            'url' => '/squadra',
            'location' => 'main',
        ]);

        StaffMember::create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'role' => 'Allenatore',
            'type' => StaffType::Tecnico,
            'section' => 'a1',
        ]);
        StaffMember::create([
            'first_name' => 'Luca',
            'last_name' => 'Bianchi',
            'role' => 'Preparatore',
            'type' => StaffType::Tecnico,
            'section' => 'youth',
        ]);
        StaffMember::create([
            'first_name' => 'Anna',
            'last_name' => 'Verdi',
            'role' => 'Presidente',
            'type' => StaffType::Dirigenza,
            'section' => 'a1',
        ]);

        NewsletterSubscriber::create([
            'email' => 'test@example.com',
            'source' => 'website',
            'subscribed_at' => now(),
        ]);
    }

    public static function listPageProvider(): array
    {
        return [
            'News' => [Resources\PostResource\Pages\ListPosts::class],
            'Pagine' => [Resources\PageResource\Pages\ListPages::class],
            'Categorie' => [Resources\CategoryResource\Pages\ListCategories::class],
            'Voci menu' => [Resources\MenuItemResource\Pages\ListMenuItems::class],
            'Sponsor' => [Resources\SponsorResource\Pages\ListSponsors::class],
            'Squadre' => [Resources\TeamResource\Pages\ListTeams::class],
            'Atleti' => [Resources\PlayerResource\Pages\ListPlayers::class],
            'Statistiche' => [Resources\PlayerStatResource\Pages\ListPlayerStats::class],
            'Roster' => [Resources\RosterResource\Pages\ListRosters::class],
            'Roster youth' => [Resources\YouthRosterResource\Pages\ListYouthRosters::class],
            'Staff' => [Resources\StaffMemberResource\Pages\ListStaffMembers::class],
            'Staff youth' => [Resources\YouthStaffResource\Pages\ListYouthStaff::class],
            'Organigramma' => [Resources\ManagementResource\Pages\ListManagement::class],
            'Partite' => [Resources\GameResource\Pages\ListGames::class],
            'Stagioni' => [Resources\SeasonResource\Pages\ListSeasons::class],
            'Eventi galleria' => [Resources\GalleryEventResource\Pages\ListGalleryEvents::class],
            'Immagini galleria' => [Resources\GalleryImageResource\Pages\ListGalleryImages::class],
            'Messaggi contatti' => [Resources\ContactMessageResource\Pages\ManageContactMessages::class],
            'Accrediti stampa' => [Resources\PressAccreditationResource\Pages\ManagePressAccreditations::class],
            'Newsletter' => [Resources\NewsletterSubscriberResource\Pages\ListNewsletterSubscribers::class],
            'Log attività' => [Resources\ActivityLogResource\Pages\ListActivityLogs::class],
        ];
    }

    #[DataProvider('listPageProvider')]
    public function test_list_page_renders(string $page): void
    {
        Livewire::actingAs($this->admin)
            ->test($page)
            ->assertSuccessful();
    }

    public function test_translatable_edit_pages_expose_the_locale_switcher(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Resources\PostResource\Pages\EditPost::class, ['record' => Post::first()->getKey()])
            ->assertSuccessful()
            ->assertActionExists('activeLocale');

        Livewire::actingAs($this->admin)
            ->test(Resources\MenuItemResource\Pages\EditMenuItem::class, ['record' => MenuItem::first()->getKey()])
            ->assertSuccessful()
            ->assertActionExists('activeLocale');
    }
}
