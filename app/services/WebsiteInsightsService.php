<?php

declare(strict_types=1);

function website_insights_for_domain(string $domain): array
{
    $normalized = normalize_homepage_url($domain);

    if ($normalized === null) {
        return [
            'ok' => false,
            'message' => 'Please enter a valid website domain.',
        ];
    }

    $homepage = fetch_homepage_text($normalized);

    if (!$homepage['ok']) {
        return $homepage;
    }

    return extract_website_insights($normalized, $homepage['text']);
}

function normalize_homepage_url(string $domain): ?string
{
    $domain = trim($domain);
    $domain = preg_replace('/\s+/', '', $domain) ?? '';

    if ($domain === '') {
        return null;
    }

    if (!preg_match('#^https?://#i', $domain)) {
        $domain = 'https://' . $domain;
    }

    $parts = parse_url($domain);
    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($host === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)) {
        return null;
    }

    if (is_private_or_local_host($host)) {
        return null;
    }

    return 'https://' . $host . '/';
}

function is_private_or_local_host(string $host): bool
{
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
        return true;
    }

    $records = dns_get_record($host, DNS_A + DNS_AAAA);

    foreach ($records ?: [] as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? '';

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
    }

    return false;
}

function fetch_homepage_text(string $url): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'FiredTheAgencyBot/1.0 (+https://clients.firedtheagency.com)',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $html = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (!is_string($html) || $html === '' || $status >= 400) {
        return [
            'ok' => false,
            'message' => $error !== '' ? $error : 'Could not fetch that website homepage.',
        ];
    }

    if ($contentType !== '' && !str_contains(strtolower($contentType), 'text/html')) {
        return [
            'ok' => false,
            'message' => 'That URL did not return an HTML homepage.',
        ];
    }

    $text = html_to_prompt_text($html);

    if ($text === '') {
        return [
            'ok' => false,
            'message' => 'Could not read useful text from that homepage.',
        ];
    }

    return [
        'ok' => true,
        'text' => mb_substr($text, 0, 9000),
    ];
}

function html_to_prompt_text(string $html): string
{
    $html = preg_replace('#<(script|style|noscript|svg|iframe)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    $html = preg_replace('#<!--.*?-->#s', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function extract_website_insights(string $url, string $homepageText): array
{
    $config = require APP_ROOT . '/app/config/app.php';
    $apiKey = trim((string) ($config['ai']['openai_api_key'] ?? ''));

    if ($apiKey === '') {
        return [
            'ok' => false,
            'message' => 'OpenAI is not configured.',
        ];
    }

    $payload = [
        'model' => $config['ai']['model'] ?? 'gpt-4o-mini',
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Extract concise marketing setup data from homepage text. Return only valid JSON with keys: service, why_choose, city. service must be the single highest-level service in max 3 words. why_choose must be a newline-separated bullet list of concrete USPs found or strongly implied. city must be the main service city/suburb and state/country if available, otherwise empty.',
            ],
            [
                'role' => 'user',
                'content' => "URL: {$url}\n\nHomepage text:\n{$homepageText}",
            ],
        ],
        'temperature' => 0.2,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status >= 400) {
        return [
            'ok' => false,
            'message' => $error !== '' ? $error : 'AI analysis failed.',
        ];
    }

    $decoded = json_decode($body, true);
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    $insights = is_string($content) ? json_decode($content, true) : null;

    if (!is_array($insights)) {
        return [
            'ok' => false,
            'message' => 'AI returned an unreadable response.',
        ];
    }

    return [
        'ok' => true,
        'service' => sanitize_ai_field($insights['service'] ?? '', 80),
        'why_choose' => sanitize_ai_field($insights['why_choose'] ?? '', 800),
        'city' => sanitize_ai_field($insights['city'] ?? '', 120),
    ];
}

function sanitize_ai_field(mixed $value, int $maxLength): string
{
    if (is_array($value)) {
        $value = implode("\n", array_map(static fn ($item) => '- ' . trim((string) $item), $value));
    }

    $value = trim((string) $value);
    $value = preg_replace('/[^\P{C}\r\n\t]+/u', '', $value) ?? $value;

    return mb_substr($value, 0, $maxLength);
}

function handle_website_insights(array $brand): void
{
    require_auth($brand);

    header('Content-Type: application/json');

    $domain = post_value('domain');

    if (mb_strlen($domain) <= 5) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Domain is too short.',
        ]);
        exit;
    }

    echo json_encode(website_insights_for_domain($domain));
    exit;
}
