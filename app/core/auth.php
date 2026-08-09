<?php

declare(strict_types=1);

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'brand_id' => (int) $user['brand_id'],
        'business_id' => (int) $user['business_id'],
        'name' => $user['name'],
        'email' => $user['email'],
    ];
}

function require_auth(array $brand): void
{
    $user = auth_user();

    if (!$user || (int) $user['brand_id'] !== (int) $brand['id']) {
        redirect(brand_url($brand, 'login'));
    }
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
