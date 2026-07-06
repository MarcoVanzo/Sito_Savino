<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\SiteSetting;
use App\Services\AuctionService;
use App\Services\BidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuctionController extends Controller
{
    public function __construct(
        private readonly BidService $bidService,
    ) {}

    /**
     * Lista aste (pubblica).
     */
    public function index()
    {
        $auctions = Auction::with(['product.media'])
            ->whereIn('status', ['active', 'scheduled', 'ended'])
            ->withCount(['bids' => fn ($q) => $q->valid()])
            ->orderByRaw("FIELD(status, 'active', 'scheduled', 'ended')")
            ->orderBy('end_date', 'asc')
            ->get()
            ->map(fn ($auction) => $this->formatAuctionForList($auction));

        return Inertia::render('Public/Shop/Auctions/Index', [
            'auctions' => $auctions,
            'rulesText' => SiteSetting::get('auctions.rules_text'),
        ]);
    }

    /**
     * Dettaglio asta singola (pubblica).
     */
    public function show(Auction $auction)
    {
        $auction->load(['product.media', 'product.variants', 'bids' => fn ($q) => $q->valid()->highestFirst()->with('user:id,name')->take(20)]);
        $auction->loadCount(['bids' => fn ($q) => $q->valid()]);

        $user = auth()->user();
        $userIsHighestBidder = false;

        if ($user) {
            $highestBid = $auction->highestBid();
            $userIsHighestBidder = $highestBid && $highestBid->user_id === $user->id;
        }

        return Inertia::render('Public/Shop/Auctions/Show', [
            'auction' => $this->formatAuctionForDetail($auction),
            'bids' => $auction->bids->map(fn ($bid) => [
                'bidder' => AuctionService::maskUsername($bid->user->name ?? 'Anonimo'),
                'amount' => (float) $bid->amount,
                'placed_at' => $bid->placed_at->toIso8601String(),
                'is_mine' => $user && $bid->user_id === $user->id,
            ]),
            'userIsHighestBidder' => $userIsHighestBidder,
            'hasVerifiedPayment' => $user?->has_verified_payment_method ?? false,
            'rulesText' => SiteSetting::get('auctions.rules_text'),
        ]);
    }

    /**
     * Piazza un'offerta (autenticato + carta verificata).
     */
    public function bid(Request $request, Auction $auction)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $user = $request->user();

        try {
            $bid = $this->bidService->placeBid(
                $auction,
                $user,
                (float) $request->input('amount'),
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('messages.auction.bid_placed'),
                    'bid' => [
                        'amount' => (float) $bid->amount,
                        'placed_at' => $bid->placed_at->toIso8601String(),
                    ],
                ]);
            }

            return back()->with('success', __('messages.auction.bid_placed'));

        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint polling leggero per aggiornamenti real-time (API pubblica).
     */
    public function status(Auction $auction): JsonResponse
    {
        $latestBids = $auction->validBids()
            ->with('user:id,name')
            ->take(10)
            ->get()
            ->map(fn ($bid) => [
                'bidder' => AuctionService::maskUsername($bid->user->name ?? 'Anonimo'),
                'amount' => (float) $bid->amount,
                'placed_at' => $bid->placed_at->toIso8601String(),
            ]);

        return response()->json([
            'current_bid' => $auction->current_bid ? (float) $auction->current_bid : null,
            'bids_count' => $auction->bids()->valid()->count(),
            'end_date' => $auction->end_date->toIso8601String(),
            'minimum_bid' => $auction->minimumBidAmount(),
            'maximum_bid' => $auction->maximumBidAmount(),
            'is_active' => $auction->isActive(),
            'reserve_met' => $auction->isReserveMet(),
            'latest_bids' => $latestBids,
        ]);
    }

    // --- Formatter privati ---

    private function formatAuctionForList(Auction $auction): array
    {
        $product = $auction->product;

        return [
            'id' => $auction->id,
            'title' => $auction->title,
            'status' => $auction->status->value,
            'starting_price' => (float) $auction->starting_price,
            'current_bid' => $auction->current_bid ? (float) $auction->current_bid : null,
            'bids_count' => $auction->bids_count,
            'start_date' => $auction->start_date->toIso8601String(),
            'end_date' => $auction->end_date->toIso8601String(),
            'is_charity' => $auction->is_charity,
            'is_active' => $auction->isActive(),
            'reserve_met' => $auction->isReserveMet(),
            'image' => $product?->getFirstMediaUrl('images', 'medium') ?: null,
            'slug' => $auction->id, // Per ora usiamo l'ID come identificativo nell'URL
        ];
    }

    private function formatAuctionForDetail(Auction $auction): array
    {
        $product = $auction->product;
        
        $sizes = $product?->variants->pluck('size')->filter()->unique()->values()->all() ?? [];
        $productSize = $auction->size ?: (count($sizes) > 0 ? implode(', ', $sizes) : null);

        return [
            ...$this->formatAuctionForList($auction),
            'description' => $auction->description,
            'charity_description' => $auction->charity_description,
            'bid_increment' => (float) $auction->bid_increment,
            'max_bid_jump' => (float) $auction->max_bid_jump,
            'minimum_bid' => $auction->minimumBidAmount(),
            'maximum_bid' => $auction->maximumBidAmount(),
            'has_reserve' => ! is_null($auction->reserve_price),
            'images' => $product?->getMedia('images')->map(fn ($media) => [
                'original' => $media->getUrl(),
                'medium' => $media->getUrl('medium'),
                'thumbnail' => $media->getUrl('thumbnail'),
            ]) ?? [],
            'product_name' => $product?->name,
            'product_description' => $product?->description,
            'product_size' => $productSize,
        ];
    }
}
