<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Price;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createDynamicPrice(int $amountCents, string $productName): string
    {
        $price = Price::create([
            'unit_amount' => $amountCents,
            'currency' => 'brl',
            'product_data' => [
                'name' => $productName,
            ],
        ]);

        return $price->id;
    }

    public function createCheckoutSession(
        string $priceId,
        int $userId,
        int $checkoutId,
        ?string $stripeCouponId = null
    ): Session {
        $params = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('subscription.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('subscription.checkout.cancel'),
            'client_reference_id' => $checkoutId,
            'metadata' => [
                'user_id' => $userId,
                'checkout_id' => $checkoutId,
            ],
        ];

        if ($stripeCouponId) {
            $params['discounts'] = [['coupon' => $stripeCouponId]];
        }

        return Session::create($params);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }
}