<?php

namespace Tests\Unit\Models;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase;

    public function test_auction_belongs_to_product(): void
    {
        $product = Product::factory()->create();
        $auction = Auction::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $auction->product);
        $this->assertEquals($product->id, $auction->product->id);
    }

    public function test_auction_has_many_bids(): void
    {
        $auction = Auction::factory()->create();
        Bid::factory()->create(['auction_id' => $auction->id]);

        $this->assertCount(1, $auction->bids);
        $this->assertInstanceOf(Bid::class, $auction->bids->first());
    }

    public function test_active_scope(): void
    {
        $active = Auction::factory()->active()->create();
        $active->refresh();
        Auction::factory()->create(); // Scheduled by default

        $results = Auction::active()->pluck('id')->toArray();

        $this->assertContains($active->id, $results);
        $this->assertCount(1, $results);
    }

    public function test_is_active_returns_true_for_active_auction(): void
    {
        $auction = Auction::factory()->active()->create();
        $auction->refresh();

        $this->assertTrue($auction->isActive());
    }

    public function test_is_reserve_met_without_reserve_price(): void
    {
        $auction = Auction::factory()->create(['reserve_price' => null]);

        $this->assertTrue($auction->isReserveMet());
    }

    public function test_is_reserve_met_with_reserve_met(): void
    {
        $auction = Auction::factory()->create([
            'reserve_price' => 100.00,
            'current_bid' => 150.00,
        ]);

        $this->assertTrue($auction->isReserveMet());
    }

    public function test_is_reserve_met_with_reserve_not_met(): void
    {
        $auction = Auction::factory()->create([
            'reserve_price' => 200.00,
            'current_bid' => 50.00,
        ]);

        $this->assertFalse($auction->isReserveMet());
    }

    public function test_minimum_bid_amount_from_starting_price(): void
    {
        $auction = Auction::factory()->create([
            'starting_price' => 100.00,
            'bid_increment' => 10.00,
            'current_bid' => null,
        ]);

        $this->assertEquals(110.00, $auction->minimumBidAmount());
    }

    public function test_minimum_bid_amount_from_current_bid(): void
    {
        $auction = Auction::factory()->create([
            'starting_price' => 100.00,
            'current_bid' => 150.00,
            'bid_increment' => 10.00,
        ]);

        $this->assertEquals(160.00, $auction->minimumBidAmount());
    }

    public function test_maximum_bid_amount(): void
    {
        $auction = Auction::factory()->create([
            'starting_price' => 100.00,
            'current_bid' => 150.00,
            'max_bid_jump' => 50.00,
        ]);

        $this->assertEquals(200.00, $auction->maximumBidAmount());
    }

    public function test_auction_uses_soft_deletes(): void
    {
        $auction = Auction::factory()->create();
        $auctionId = $auction->id;

        $auction->delete();

        $this->assertSoftDeleted('auctions', ['id' => $auctionId]);
        $this->assertNotNull(Auction::withTrashed()->find($auctionId));
    }
}
