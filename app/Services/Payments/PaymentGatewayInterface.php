<?php

namespace App\Services\Payments;

use App\Models\Order;

interface PaymentGatewayInterface
{
    /**
     * Create a payment session/order and return the redirect URL.
     */
    public function createSession(Order $order): string;

    /**
     * Process the webhook payload and return the payment ID + status.
     *
     * @param  string  $rawBody  Raw request body (needed for signature verification)
     * @param  array  $headers  Request headers
     * @return array{payment_id?: string, status: string, order_id?: int|string}
     */
    public function handleWebhook(string $rawBody, array $headers): array;

    /**
     * Issue a full refund for the order.
     *
     * @return bool True on success
     *
     * @throws \Exception on failure
     */
    public function refund(Order $order): bool;
}
