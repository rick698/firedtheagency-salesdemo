<?php

declare(strict_types=1);

function payment_mode(): string
{
    return 'test_mock';
}

function stripe_checkout_ready(): bool
{
    $config = require APP_ROOT . '/app/config/app.php';

    return !empty($config['stripe']['secret_key']);
}

function amount_with_gst_cents(int $amountCents): int
{
    return (int) round($amountCents * 1.10);
}

function create_stripe_checkout_session(array $brand, array $user, array $business, array $campaign, string $plan): array
{
    $config = require APP_ROOT . '/app/config/app.php';
    $secretKey = $config['stripe']['secret_key'] ?? '';

    if ($secretKey === '') {
        return [
            'ok' => false,
            'message' => 'Stripe is not configured yet.',
        ];
    }

    $stripeInit = APP_ROOT . '/app/vendor/stripe-php-19.3.0/init.php';

    if (!is_file($stripeInit)) {
        return [
            'ok' => false,
            'message' => 'Stripe library is not installed.',
        ];
    }

    require_once $stripeInit;
    \Stripe\Stripe::setApiKey($secretKey);

    $managementFeeBaseCents = $plan === 'pro' ? 13900 : 6700;
    $setupFeeBaseCents = 9700;
    $managementFeeCents = amount_with_gst_cents($managementFeeBaseCents);
    $setupFeeCents = amount_with_gst_cents($setupFeeBaseCents);
    $currency = $config['stripe']['currency'] ?? 'aud';
    $successUrl = brand_url($brand, 'checkout-success') . '&session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl = brand_url($brand, 'project-complete') . '&checkout=cancelled';

    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => 'campaign_' . (int) $campaign['id'],
            'customer_email' => $user['email'],
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $managementFeeCents,
                        'recurring' => [
                            'interval' => 'month',
                        ],
                        'product_data' => [
                            'name' => 'Management Fee including GST',
                        ],
                    ],
                ],
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $setupFeeCents,
                        'product_data' => [
                            'name' => 'Setup Fee including GST',
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'brand_id' => (string) $brand['id'],
                'business_id' => (string) $business['id'],
                'campaign_id' => (string) $campaign['id'],
                'user_id' => (string) $user['id'],
                'plan' => $plan,
                'management_fee_base_cents' => (string) $managementFeeBaseCents,
                'management_fee_gst_cents' => (string) ($managementFeeCents - $managementFeeBaseCents),
                'setup_fee_base_cents' => (string) $setupFeeBaseCents,
                'setup_fee_gst_cents' => (string) ($setupFeeCents - $setupFeeBaseCents),
            ],
            'subscription_data' => [
                'metadata' => [
                    'brand_id' => (string) $brand['id'],
                    'business_id' => (string) $business['id'],
                    'campaign_id' => (string) $campaign['id'],
                    'user_id' => (string) $user['id'],
                    'plan' => $plan,
                    'management_fee_base_cents' => (string) $managementFeeBaseCents,
                    'management_fee_gst_cents' => (string) ($managementFeeCents - $managementFeeBaseCents),
                ],
            ],
        ]);

        return [
            'ok' => true,
            'id' => $session->id,
            'url' => $session->url,
        ];
    } catch (\Throwable $exception) {
        return [
            'ok' => false,
            'message' => $exception->getMessage(),
        ];
    }
}

function charge_monthly_ad_spend_package(array $brand, array $subscription, array $campaign, int $amountCents): array
{
    $config = require APP_ROOT . '/app/config/app.php';
    $secretKey = $config['stripe']['secret_key'] ?? '';
    $stripeInit = APP_ROOT . '/app/vendor/stripe-php-19.3.0/init.php';

    if ($secretKey === '' || !is_file($stripeInit)) {
        return [
            'ok' => false,
            'message' => 'Stripe is not configured yet.',
        ];
    }

    require_once $stripeInit;
    \Stripe\Stripe::setApiKey($secretKey);
    $baseAmountCents = $amountCents;
    $amountCents = amount_with_gst_cents($baseAmountCents);

    $stripeCustomerId = $subscription['stripe_customer_id'] ?? '';
    $stripeSubscriptionId = $subscription['stripe_subscription_id'] ?? '';

    if ($stripeCustomerId === '' || $stripeSubscriptionId === '') {
        return [
            'ok' => false,
            'message' => 'Stripe customer details are not ready yet. Please try again in a moment.',
        ];
    }

    try {
        $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
        $paymentMethod = !empty($stripeSubscription->default_payment_method)
            ? (string) $stripeSubscription->default_payment_method
            : '';

        if ($paymentMethod === '') {
            $customer = \Stripe\Customer::retrieve($stripeCustomerId);
            $paymentMethod = !empty($customer->invoice_settings->default_payment_method)
                ? (string) $customer->invoice_settings->default_payment_method
                : '';
        }

        if ($paymentMethod === '') {
            return [
                'ok' => false,
                'message' => 'Could not find the saved Stripe payment method yet. Please try again in a moment.',
            ];
        }

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amountCents,
            'currency' => $config['stripe']['currency'] ?? 'aud',
            'customer' => $stripeCustomerId,
            'payment_method' => $paymentMethod,
            'off_session' => true,
            'confirm' => true,
            'description' => 'Monthly ad spend package including GST',
            'metadata' => [
                'brand_id' => (string) $brand['id'],
                'business_id' => (string) $subscription['business_id'],
                'campaign_id' => (string) $campaign['id'],
                'subscription_id' => (string) $subscription['id'],
                'charge_type' => 'monthly_ad_spend_package',
                'base_amount_cents' => (string) $baseAmountCents,
                'gst_cents' => (string) ($amountCents - $baseAmountCents),
            ],
        ]);

        if (($paymentIntent->status ?? '') !== 'succeeded') {
            return [
                'ok' => false,
                'message' => 'Stripe returned payment status: ' . ($paymentIntent->status ?? 'unknown'),
            ];
        }

        return [
            'ok' => true,
            'payment_intent_id' => $paymentIntent->id,
            'status' => $paymentIntent->status,
            'base_amount_cents' => $baseAmountCents,
            'charged_amount_cents' => $amountCents,
            'gst_cents' => $amountCents - $baseAmountCents,
        ];
    } catch (\Throwable $exception) {
        return [
            'ok' => false,
            'message' => $exception->getMessage(),
        ];
    }
}

function retrieve_stripe_checkout_session(string $sessionId): ?object
{
    $config = require APP_ROOT . '/app/config/app.php';
    $secretKey = $config['stripe']['secret_key'] ?? '';
    $stripeInit = APP_ROOT . '/app/vendor/stripe-php-19.3.0/init.php';

    if ($secretKey === '' || !is_file($stripeInit)) {
        return null;
    }

    require_once $stripeInit;
    \Stripe\Stripe::setApiKey($secretKey);

    try {
        return \Stripe\Checkout\Session::retrieve($sessionId);
    } catch (\Throwable $exception) {
        return null;
    }
}
