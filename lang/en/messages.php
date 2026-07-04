<?php

return [
    'validation' => [
        'name_required' => 'Name is required.',
        'email_required' => 'Email is required.',
        'email_invalid' => 'Please enter a valid email address.',
        'email_max' => 'Email cannot exceed 255 characters.',
        'message_required' => 'Message is required.',
        'message_max' => 'Message cannot exceed 5000 characters.',
        'privacy_accepted' => 'You must accept the privacy policy.',
    ],
    'contact' => [
        'success_bot' => 'Message sent successfully!',
        'success_human' => 'Message sent successfully! We will get back to you as soon as possible.',
    ],
    'cart' => [
        'added' => 'Product added to cart.',
        'updated' => 'Quantity updated.',
        'removed' => 'Product removed from cart.',
        'empty' => 'Your cart is empty.',
        'product_unavailable' => 'Product is not available.',
        'auction_not_allowed' => 'Auction products cannot be added to the cart.',
        'invalid_variant' => 'This variant does not belong to the selected product.',
        'max_qty' => 'The maximum quantity per product is :qty.',
        'out_of_stock' => 'Insufficient stock. Available stock: :stock.',
        'not_found' => 'Cart not found.',
    ],
    'checkout' => [
        'error' => 'An error occurred during checkout. Please try again.',
        'success_bank' => 'Order created successfully! We have sent you the bank transfer instructions.',
    ],
    'shop' => [
        'register_success' => 'Registration completed! Welcome to the shop.',
        'register' => 'Register',
        'register_description' => 'Create an account to manage your orders and speed up checkout.',
        'full_name' => 'Full Name',
        'email' => 'Email',
        'password' => 'Password',
        'password_confirmation' => 'Confirm Password',
        'accept_privacy_1' => 'I accept the',
        'accept_privacy_2' => 'Privacy Policy',
        'create_account' => 'Create Account',
        'already_have_account' => 'Already have an account?',
        'login_now' => 'Login now',
    ],
    'newsletter' => [
        'already_subscribed' => 'This email is already subscribed to the newsletter.',
        'success' => 'Subscription completed! Thank you for subscribing.',
    ],
];
