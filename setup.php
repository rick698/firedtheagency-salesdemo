<?php

declare(strict_types=1);

require_once __DIR__ . '/app/core/bootstrap.php';

$brand = current_brand($brands, 'firedtheagency');
$page = $_GET['page'] ?? 'login';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = rtrim(parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '', '/') ?: '/';

if ($requestPath === '/agency' || $page === 'agency') {
    if ($method === 'POST') {
        handle_agency_login();
        exit;
    }

    show_agency();
    exit;
}

if ($page === 'agency-logout') {
    handle_agency_logout();
    exit;
}

if (isset($_GET['start']) && $method === 'GET') {
    start_demo_dashboard($brand);
}

if ($page === 'subscribe' && $method === 'POST') {
    start_subscription($brand);
}

if ($page === 'register' && $method === 'POST') {
    register_user($brand);
    exit;
}

if ($page === 'login' && $method === 'POST') {
    login_with_password($brand);
    exit;
}

if ($page === 'create-project' && $method === 'POST') {
    save_project($brand);
    exit;
}

if ($page === 'create-project-step' && $method === 'POST') {
    save_project_step($brand);
    exit;
}

if ($page === 'website-insights' && $method === 'POST') {
    handle_website_insights($brand);
    exit;
}

if ($page === 'stripe-checkout' && $method === 'POST') {
    start_stripe_checkout($brand);
    exit;
}

if ($page === 'tax-details' && $method === 'POST') {
    save_tax_details($brand);
    exit;
}

if ($page === 'confirm-ad-spend-budget' && $method === 'POST') {
    confirm_ad_spend_budget($brand);
    exit;
}

if ($page === 'profile' && $method === 'POST') {
    save_profile($brand);
    exit;
}

if ($page === 'stripe-webhook' && $method === 'POST') {
    handle_stripe_webhook($brands);
    exit;
}

if ($page === 'logout') {
    handle_logout($brand);
}

match ($page) {
    'register' => show_register($brand),
    'login' => show_login($brand),
    'dashboard' => show_dashboard($brand),
    'create-project' => show_create_project($brand),
    'project-complete' => show_project_complete($brand),
    'checkout-success' => handle_checkout_success($brand),
    'profile' => show_profile($brand),
    'budget' => show_budget($brand),
    'invoices' => show_invoices($brand),
    'TAC' => show_terms($brand),
    'thank-you' => show_thank_you($brand),
    default => show_login($brand),
};
