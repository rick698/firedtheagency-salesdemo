<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function brand_url(array $brand, ?string $page = null): string
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $entryPath = app_entry_path();

    if (
        $host === '127.0.0.1'
        || $host === 'localhost'
        || $host === 'smallbusinessdigitalservices.com.au'
        || $host === 'www.smallbusinessdigitalservices.com.au'
        || $host === 'firedtheagency.com'
        || $host === 'www.firedtheagency.com'
        || str_ends_with($entryPath, '/setup.php')
        || str_starts_with($entryPath, '/demo/')
        || str_starts_with($host, '127.0.0.1:')
        || str_starts_with($host, 'localhost:')
    ) {
        $baseUrl = $scheme . '://' . $host;
    } else {
        $baseUrl = 'https://' . $brand['domain'];
    }

    $url = $baseUrl . $entryPath;
    $query = tracking_query_params();

    if ($page !== null && $page !== 'landing') {
        $query = array_merge(['page' => $page], $query);
    }

    return $query ? $url . '?' . http_build_query($query) : $url;
}

function app_entry_path(): string
{
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

    if (str_ends_with($scriptName, '/setup.php') || str_ends_with($scriptName, '/index.php')) {
        return $scriptName;
    }

    return '/index.php';
}

function tracking_query_params(): array
{
    $reserved = [
        'page', 'checkout', 'checkout_error', 'session_id', 'saved', 'new',
        'lead', 'token', 'PHPSESSID',
    ];
    $allowedExact = ['tracking', 'source', 'campaign', 'thank_you', 'thankyou', 'ref'];
    $params = [];

    foreach ($_GET as $key => $value) {
        if (in_array($key, $reserved, true)) {
            continue;
        }

        $isAllowed = in_array($key, $allowedExact, true)
            || str_starts_with($key, 'utm_')
            || in_array($key, ['gclid', 'fbclid', 'msclkid'], true);

        if ($isAllowed && is_scalar($value)) {
            $params[$key] = (string) $value;
        }
    }

    if (!empty($params)) {
        $_SESSION['tracking_query_params'] = $params;
        return $params;
    }

    return $_SESSION['tracking_query_params'] ?? [];
}

function configured_thank_you_url(array $brand): string
{
    $params = tracking_query_params();
    $thankYou = trim((string) ($params['thank_you'] ?? $params['thankyou'] ?? ''));

    if ($thankYou !== '') {
        if (str_starts_with($thankYou, 'https://')) {
            return $thankYou;
        }

        if (str_starts_with($thankYou, '/')) {
            return $thankYou;
        }

        return brand_url($brand, 'thank-you') . '&variant=' . rawurlencode($thankYou);
    }

    return brand_url($brand, 'thank-you');
}

function current_brand(array $brands, string $brandSlug): array
{
    if (!isset($brands[$brandSlug])) {
        http_response_code(404);
        exit('Brand not configured.');
    }

    return $brands[$brandSlug];
}

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/app/views/' . $template . '.php';
}

function post_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}
