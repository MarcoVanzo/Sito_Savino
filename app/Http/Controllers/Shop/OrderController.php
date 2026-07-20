<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OrderController extends Controller
{
    public function __construct(
        protected ReceiptService $receiptService,
    ) {}

    /**
     * I miei ordini (solo utenti autenticati).
     */
    public function index(Request $request): Response
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['items.product'])
            ->latest()
            ->paginate(10);

        return Inertia::render('Public/Shop/MyOrders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Dettaglio ordine.
     * Utenti autenticati: verifica user_id.
     * Guest: richiede ?token= query param.
     */
    public function show(Request $request, string $orderNumber): Response
    {
        $order = $this->resolveOrder($request, $orderNumber);

        $order->load(['items.product', 'items.variant', 'coupon']);

        return Inertia::render('Public/Shop/OrderDetail', [
            'order' => $order,
        ]);
    }

    /**
     * Download ricevuta PDF.
     */
    public function downloadReceipt(Request $request, string $orderToken): SymfonyResponse
    {
        $order = Order::where('order_token', $orderToken)->firstOrFail();

        // Verifica accesso: utente autenticato può scaricare solo i propri ordini
        if (auth()->check() && $order->user_id !== auth()->id()) {
            abort(403);
        }
        // Guest: l'unica protezione è il token UUID nell'URL (alta entropia)

        return $this->receiptService->download($order);
    }

    /**
     * Risolve l'ordine in base al tipo di utente (auth o guest).
     */
    private function resolveOrder(Request $request, string $orderNumber): Order
    {
        if (auth()->check()) {
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', auth()->id())
                ->first();

            if ($order) {
                return $order;
            }
        }

        // Fallback per guest: richiede token
        $token = $request->query('token');

        if (! $token) {
            abort(404);
        }

        return Order::where('order_number', $orderNumber)
            ->where('order_token', $token)
            ->firstOrFail();
    }
}
