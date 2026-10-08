<?php

namespace Tests\Unit\Models;

use App\Enums\SponsorTier;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SponsorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sponsor_factory_creates_valid_record(): void
    {
        $sponsor = Sponsor::factory()->create();

        $this->assertDatabaseHas('sponsors', ['id' => $sponsor->id]);
    }

    #[Test]
    public function tier_is_cast_to_enum(): void
    {
        $sponsor = Sponsor::factory()->create(['tier' => SponsorTier::Gold]);

        $this->assertInstanceOf(SponsorTier::class, $sponsor->tier);
        $this->assertEquals(SponsorTier::Gold, $sponsor->tier);
    }

    #[Test]
    public function sponsor_fillable_fields(): void
    {
        $sponsor = Sponsor::create([
            'name' => 'Test Sponsor',
            'url' => 'https://example.com',
            'tier' => SponsorTier::Gold,
            'sort_order' => 1,
        ]);

        $this->assertEquals('Test Sponsor', $sponsor->name);
        $this->assertEquals('https://example.com', $sponsor->url);
    }
}
