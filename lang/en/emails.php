<?php

/*
 * Transactional shop email copy. See lang/it/emails.php for how the locale is
 * resolved: the mails are queued, so each Mailable declares its own locale from
 * `orders.locale` (or `users.locale` for auctions) instead of relying on the
 * request.
 */

return [
    'layout' => [
        'auto_generated' => 'This email was generated automatically. Please do not reply to this address.',
    ],

    'common' => [
        'order_number' => 'Order Number',
        'order_date' => 'Order Date',
        'date' => 'Date',
        'status' => 'Status',
        'amount' => 'Amount',
        'total' => 'Total',
        'order' => 'Order',
        'view_order' => 'View your order →',
        'order_details' => 'Order details →',
        'removed_product' => 'Product removed',
    ],

    'confirmation' => [
        'subject' => 'Order Confirmation #:number',
        'title' => 'Order Confirmation #:number',
        'heading' => 'Thank you for your order!',
        'intro' => 'We have received your order and we are processing it.',
        'items_heading' => 'Items ordered',
        'col_product' => 'Product',
        'col_quantity' => 'Qty',
        'col_price' => 'Price',
        'col_total' => 'Total',
        'subtotal' => 'Subtotal',
        'shipping' => 'Shipping',
        'coupon_discount' => 'Coupon discount',
        'bank_transfer_heading' => '📋 Bank Transfer Instructions',
        'bank_beneficiary' => 'Beneficiary:',
        'bank_iban' => 'IBAN:',
        'bank_reason' => 'Reference:',
        'bank_reason_value' => 'Order :number',
        'bank_deadline' => 'Please make the payment within :days working days from the order date. Otherwise the order will be cancelled automatically.',
        'cta_intro' => 'You can follow your order at any time:',
    ],

    'shipped' => [
        'subject' => 'Your order #:number has been shipped!',
        'title' => 'Order #:number shipped',
        'heading' => '🚚 Your order is on its way!',
        'intro' => 'Great news! Your order has been shipped.',
        'tracking_number' => 'Tracking Number',
        'shipped_at' => 'Shipping Date',
        'track_button' => '📦 Track your shipment',
        'delivery_estimate_label' => 'Estimated delivery time:',
        'delivery_estimate' => 'shipping usually takes 2-5 working days, depending on the destination.',
        'cta_intro' => 'You can check your order details:',
    ],

    'status_changed' => [
        'subject' => 'Order Update #:number — :status',
        'title' => 'Order Update #:number',
        'processing_heading' => 'Your order is being processed',
        'processing_intro' => 'We have taken charge of your order and we are preparing it for shipping.',
        'delivered_heading' => 'Order delivered! ✅',
        'delivered_intro' => 'Your order has been delivered successfully. We hope you are happy with your purchase!',
        'cancelled_heading' => 'Order cancelled',
        'cancelled_intro' => 'Your order has been cancelled. If you have already paid, we will contact you about the refund.',
        'refunded_heading' => 'Refund processed',
        'refunded_intro' => 'The refund for your order has been processed. The amount will be credited to your original payment method.',
        'default_heading' => 'An update on your order',
        'default_intro' => 'The status of your order has been updated.',
        'cta_intro' => 'You can view your order details at any time:',
        'contact_note' => 'If you have any questions about your order, feel free to contact us by replying to this email or writing to',
    ],

    'cancelled' => [
        'subject' => 'Order #:number cancelled for non-payment',
        'title' => 'Order #:number cancelled',
        'heading' => 'Order cancelled',
        'intro' => 'We are sorry to inform you that your order <strong>#:number</strong> of :date has been <strong>cancelled automatically</strong> because payment was not received within the allowed time.',
        'status_value' => 'Cancelled',
        'retry' => 'If you still wish to buy these items, you can place a new order in our shop.',
        'shop_button' => 'Visit the Shop',
        'contact' => 'For any question, contact us at :email.',
    ],

    'payment_reminder' => [
        'subject' => 'Payment reminder for order #:number',
        'heading' => 'Reminder: payment pending',
        'intro' => 'Your order <strong>#:number</strong> of :date is still awaiting payment.',
        'deadline' => 'Please remember to make the bank transfer within <strong>:days days</strong> of the order date, otherwise the order will be cancelled automatically.',
        'iban' => 'IBAN',
        'beneficiary' => 'Account holder',
        'reason' => 'Reference',
        'reason_value' => 'Order #:number',
        'cta' => 'View your order',
    ],

    'refund' => [
        'subject' => 'Refund for Order #:number confirmed',
        'title' => 'Refund for Order #:number',
        'heading' => 'Refund confirmed',
        'intro' => 'The refund for your order has been processed.',
        'refunded_amount' => 'Refunded amount',
        'timing_label' => 'Timing:',
        'timing' => 'the refund will be credited to your original payment method within <strong>5-10 working days</strong>. Timings may vary depending on your bank.',
        'questions' => 'If you have any questions about the refund, please get in touch.',
    ],

    'auction_won' => [
        'subject' => 'You won the auction: :title!',
        'title' => 'You won the auction: :title',
        'heading' => '🎉 Congratulations, you won the auction!',
        'intro' => 'Your bid was the winning one. Complete the payment to secure the item.',
        'auction' => 'Auction',
        'winning_amount' => 'Winning amount',
        'payment_deadline' => 'Payment deadline',
        'cta' => 'Complete the payment',
        'warning_label' => '⚠️ Please note:',
        'warning' => 'If you do not pay by the deadline shown, the auction will be assigned to the next bidder.',
    ],

    'auction_outbid' => [
        'subject' => 'You have been outbid on: :title',
        'title' => 'You have been outbid on: :title',
        'heading' => 'Your bid has been beaten!',
        'intro' => 'Another user has placed a higher bid on the auction you are taking part in.',
        'auction' => 'Auction',
        'highest_bid' => 'Current highest bid',
        'time_left' => 'Time left',
        'ended' => 'Auction ended',
        'cta' => 'Place a higher bid',
        'footer' => 'Do not miss out! Sign in and place a new bid before the auction ends.',
    ],
];
