<?php

declare(strict_types=1);

function show_landing(array $brand): void
{
    view('public/landing', [
        'brand' => $brand,
        'title' => $brand['name'] . ' Client Portal',
    ]);
}

function start_subscription(array $brand): void
{
    $_SESSION['pending_subscription'] = [
        'brand_id' => $brand['id'],
        'status' => 'test_checkout_started',
        'created_at' => date('c'),
    ];

    redirect(brand_url($brand, 'register'));
}
