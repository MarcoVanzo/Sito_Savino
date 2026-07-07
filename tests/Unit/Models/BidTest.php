<?php

namespace Tests\Unit\Models;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidTest extends TestCase
{
    use RefreshDatabase;

    public function test_bid_belongs_to_auction(): void
    {
        $auction = Auction::factory()->create();
        $bid = Bid::factory()->create(['auction_id' => $auction->id]);

        $this->assertInstanceOf(Auction::class, $bid->auction);
        $this->assertEquals($auction->id, $bid->auction->id);
    }

    public function test_bid_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $bid = Bid::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $bid->user);
        $this->assertEquals($user->id, $bid->user->id);
    }

    public function test_valid_scope(): void
    {
        $validBid = Bid::factory()->create();
        $validBid->refresh();

        $invalidBid = Bid::factory()->create();
        $invalidBid->refresh();
        $invalidBid->invalidate();

        $results = Bid::valid()->pluck('id')->toArray();

        $this->assertContains($validBid->id, $results);
        $this->assertNotContains($invalidBid->id, $results);
    }

    public function test_highest_first_scope(): void
    {
        $auction = Auction::factory()->create();

        $low = Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 50.00]);
        $mid = Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 100.00]);
        $high = Bid::factory()->create(['auction_id' => $auction->id, 'amount' => 200.00]);

        $results = Bid::highestFirst()->pluck('id')->toArray();

        $this->assertEquals($high->id, $results[0]);
        $this->assertEquals($mid->id, $results[1]);
        $this->assertEquals($low->id, $results[2]);
    }

    public function test_invalidate_method(): void
    {
        $bid = Bid::factory()->create();
        $bid->refresh();

        $this->assertTrue($bid->is_valid);

        $bid->invalidate();
        $bid->refresh();

        $this->assertFalse($bid->is_valid);
        $this->assertNotNull($bid->invalidated_at);
    }
}
