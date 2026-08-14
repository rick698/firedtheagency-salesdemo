<?php

declare(strict_types=1);

function tracking_config(): array
{
    $config = require APP_ROOT . '/app/config/app.php';

    return is_array($config['tracking'] ?? null) ? $config['tracking'] : [];
}

function tracking_allowed_events(): array
{
    return [
        'landing_page_visit',
        'PageView',
        'ViewContent',
        'Lead',
        'InitiateCheckout',
        'watch_video',
        'demo_step_1_open',
        'demo_step_2_open',
        'demo_step_3_open',
        'demo_overview_open',
        'demo_page_1',
        'demo_page_2',
        'demo_page_3',
        'demo_overview',
        'stripe_checkout_click',
    ];
}

function handle_tracking_event(array $brand): void
{
    tracking_cors_headers();
    header('Content-Type: application/json');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $eventName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($payload['event_name'] ?? '')) ?? '';

    if (!in_array($eventName, tracking_allowed_events(), true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Unknown tracking event.']);
        exit;
    }

    $eventId = trim((string) ($payload['event_id'] ?? ''));

    if ($eventId === '') {
        $eventId = bin2hex(random_bytes(16));
    }

    $config = tracking_config();
    $metaResult = tracking_send_meta_capi($config, $brand, $eventName, $eventId, $payload);

    echo json_encode([
        'ok' => true,
        'event_id' => $eventId,
        'meta_capi' => $metaResult,
    ]);
    exit;
}

function tracking_cors_headers(): void
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

    if ($origin === '') {
        return;
    }

    $host = parse_url($origin, PHP_URL_HOST);
    $allowed = is_string($host) && (
        $host === 'firedtheagency.com'
        || $host === 'www.firedtheagency.com'
        || str_ends_with($host, '.firedtheagency.com')
    );

    if (!$allowed) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
}

function tracking_send_meta_capi(array $config, array $brand, string $eventName, string $eventId, array $payload): string
{
    $pixelId = trim((string) ($config['meta_pixel_id'] ?? ''));
    $token = trim((string) ($config['meta_capi_token'] ?? ''));

    if ($pixelId === '' || $token === '') {
        return 'not configured';
    }

    if (!function_exists('curl_init')) {
        return 'curl unavailable';
    }

    $userData = [
        'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];

    foreach (['_fbp' => 'fbp', '_fbc' => 'fbc'] as $cookieName => $fieldName) {
        if (!empty($_COOKIE[$cookieName])) {
            $userData[$fieldName] = (string) $_COOKIE[$cookieName];
        }
    }

    $user = auth_user();

    if (!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $userData['em'] = hash('sha256', strtolower(trim((string) $user['email'])));
    }

    $customData = tracking_custom_data($brand, $payload);
    $event = [
        'event_name' => $eventName,
        'event_time' => time(),
        'event_id' => $eventId,
        'action_source' => 'website',
        'event_source_url' => (string) ($payload['page_url'] ?? ''),
        'user_data' => array_filter($userData),
        'custom_data' => $customData,
    ];
    $apiPayload = ['data' => [$event]];
    $testEventCode = trim((string) ($config['meta_test_event_code'] ?? ''));

    if ($testEventCode !== '') {
        $apiPayload['test_event_code'] = $testEventCode;
    }

    $graphVersion = preg_replace('/[^a-zA-Z0-9\.]/', '', (string) ($config['meta_graph_version'] ?? 'v25.0')) ?: 'v25.0';
    $url = 'https://graph.facebook.com/' . $graphVersion . '/' . rawurlencode($pixelId) . '/events?access_token=' . rawurlencode($token);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($apiPayload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 6,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status >= 400) {
        return 'failed';
    }

    return 'sent';
}

function tracking_custom_data(array $brand, array $payload): array
{
    $allowed = [
        'step',
        'plan',
        'source',
        'content_name',
        'content_category',
        'page_path',
        'page_title',
        'video_name',
        'ga_event_name',
        'tracking',
        'campaign',
        'ref',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'page_url',
        'referrer',
    ];
    $data = [
        'brand' => $brand['slug'] ?? 'firedtheagency',
    ];

    foreach ($allowed as $key) {
        if (isset($payload[$key]) && is_scalar($payload[$key])) {
            $data[$key] = (string) $payload[$key];
        }
    }

    return $data;
}
