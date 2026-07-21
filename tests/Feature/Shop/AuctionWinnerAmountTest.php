<?php

namespace Tests\Feature\Shop;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionWinnerAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_winning_amount_is_the_top_bid_for_the_first_winner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $auction = Auction::factory()->ended()->create(['current_bid' => 200]);

        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $first->id, 'amount' => 200]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $second->id, 'amount' => 150]);

        $auction->forceFill(['winner_user_id' => $first->id])->save();

        $this->assertEquals(200.0, app(AuctionService::class)->winningAmountFor($auction->fresh()));
    }

    public function test_fallback_winner_pays_their_own_bid_not_the_top_one(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $auction = Auction::factory()->ended()->create(['current_bid' => 200]);

        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $first->id, 'amount' => 200]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $second->id, 'amount' => 150]);

        // Il primo vincitore non paga: l'asta passa al secondo offerente
        $auction->forceFill([
            'winner_user_id' => $second->id,
            'current_winner_attempt' => 2,
        ])->save();

        $this->assertEquals(150.0, app(AuctionService::class)->winningAmountFor($auction->fresh()));
    }
}
