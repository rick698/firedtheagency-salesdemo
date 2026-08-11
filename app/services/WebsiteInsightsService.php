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
    $apiKey = openai_api_key($config);

    if ($apiKey === '') {
        $diagnostics = openai_config_diagnostics($config);

        return [
            'ok' => false,
            'message' => 'OpenAI is not configured. ' . $diagnostics,
        ];
    }

    $payload = [
        'model' => $config['ai']['model'] ?? 'gpt-4o-mini',
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Extract concise marketing setup data from homepage text. Return only valid JSON with keys: service, service_description, why_choose, city. service must be the single highest-level service in max 3 words, such as plumbing, lawn care, or concrete polishing. service_description must be a newline-separated list of the main service categories or menu items the business offers, not quality claims or why-us copy. For a plumber, examples are hot water systems, leak detection, general plumbing, emergency plumbing, blocked drains. For concrete polishing, examples are concrete polishing, honed concrete, floor preparation. why_choose must be a separate newline-separated bullet list of concrete USPs, guarantees, proof points, or reasons to choose them. city must be the main service city/suburb and state/country if available, otherwise empty.',
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
        'service_description' => sanitize_ai_field($insights['service_description'] ?? '', 700),
        'why_choose' => sanitize_ai_field($insights['why_choose'] ?? '', 800),
        'city' => sanitize_ai_field($insights['city'] ?? '', 120),
    ];
}

function openai_api_key(array $config): string
{
    $aiConfig = $config['ai'] ?? [];

    foreach (['openai_api_key', 'api_key', 'key'] as $keyName) {
        $value = trim((string) ($aiConfig[$keyName] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    $envValue = trim((string) getenv('OPENAI_API_KEY'));

    return $envValue;
}

function openai_config_diagnostics(array $config): string
{
    $localConfigPath = APP_ROOT . '/app/config/local.php';
    $aiConfig = $config['ai'] ?? [];
    $presentKeys = [];

    if (is_array($aiConfig)) {
        foreach (['openai_api_key', 'api_key', 'key'] as $keyName) {
            if (array_key_exists($keyName, $aiConfig)) {
                $presentKeys[] = $keyName . '=' . (trim((string) $aiConfig[$keyName]) !== '' ? 'set' : 'empty');
            }
        }
    }

    $envStatus = getenv('OPENAI_API_KEY') ? 'set' : 'empty';
    $localStatus = is_file($localConfigPath) ? 'found' : 'missing';
    $keyStatus = $presentKeys ? implode(', ', $presentKeys) : 'none';

    return 'Config check: local.php ' . $localStatus . '; ai keys ' . $keyStatus . '; OPENAI_API_KEY ' . $envStatus . '.';
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

function generate_search_keywords(array $campaign, array $business): array
{
    $target = $campaign['target_audience_data'] ?? json_decode((string) ($campaign['target_audience'] ?? ''), true) ?: [];
    $service = trim((string) ($target['service_short'] ?? $campaign['campaign_name'] ?? 'service'));
    $serviceDescription = trim((string) ($target['service_description'] ?? ''));
    $city = setup_preview_city($target);
    $fallback = fallback_search_keywords($service, $serviceDescription, $city);
    $config = require APP_ROOT . '/app/config/app.php';
    $apiKey = openai_api_key($config);

    if ($apiKey === '') {
        return $fallback;
    }

    $payload = [
        'model' => $config['ai']['model'] ?? 'gpt-4o-mini',
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Return only valid JSON with key "keywords" containing exactly 5 Google search keywords. At least 3 must be directly related to the main service and at least 2 must come from the detailed service/menu items. Include the city/service area when natural. Keep each keyword short and search-like.',
            ],
            [
                'role' => 'user',
                'content' => "Business: " . ($business['business_name'] ?? '') . "\nMain service: {$service}\nService/menu details: {$serviceDescription}\nCity/service area: {$city}",
            ],
        ],
        'temperature' => 0.35,
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
        CURLOPT_TIMEOUT => 20,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status >= 400) {
        return $fallback;
    }

    $decoded = json_decode($body, true);
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    $insights = is_string($content) ? json_decode($content, true) : null;
    $keywords = is_array($insights) && is_array($insights['keywords'] ?? null) ? $insights['keywords'] : [];
    $keywords = array_values(array_filter(array_map(
        static fn ($keyword) => sanitize_ai_field($keyword, 70),
        $keywords
    )));

    return array_slice(array_values(array_unique(array_merge($keywords, $fallback))), 0, 5);
}

function setup_preview_city(array $target): string
{
    $serviceArea = trim((string) ($target['service_area'] ?? ''));

    if ($serviceArea === '' || str_starts_with($serviceArea, 'Custom Pin:')) {
        return 'your area';
    }

    return $serviceArea;
}

function fallback_search_keywords(string $service, string $serviceDescription, string $city): array
{
    $service = trim($service) !== '' ? trim($service) : 'service';
    $citySuffix = $city !== '' && $city !== 'your area' ? ' ' . $city : '';
    $detailItems = preg_split('/[\r\n,;]+/', $serviceDescription) ?: [];
    $detailItems = array_values(array_filter(array_map('trim', $detailItems)));
    $keywords = [
        $service . $citySuffix,
        'best ' . $service . $citySuffix,
        $service . ' near me',
    ];

    foreach ($detailItems as $item) {
        if (count($keywords) >= 5) {
            break;
        }

        $keywords[] = $item . $citySuffix;
    }

    while (count($keywords) < 5) {
        $keywords[] = $service . ' quote' . $citySuffix;
    }

    return array_slice(array_values(array_unique($keywords)), 0, 5);
}

function generate_demo_ad_preview(array $campaign, array $business): array
{
    $target = $campaign['target_audience_data'] ?? json_decode((string) ($campaign['target_audience'] ?? ''), true) ?: [];
    $goals = $campaign['goals_data'] ?? json_decode((string) ($campaign['goals'] ?? ''), true) ?: [];
    $service = trim((string) ($target['service_short'] ?? $campaign['campaign_name'] ?? 'service'));
    $serviceDescription = trim((string) ($target['service_description'] ?? ''));
    $whyChoose = trim((string) ($goals['why_choose'] ?? ''));
    $city = setup_preview_city($target);
    $fallback = fallback_demo_ad_preview($service, $city, $whyChoose);
    $config = require APP_ROOT . '/app/config/app.php';
    $apiKey = openai_api_key($config);

    if ($apiKey === '') {
        return $fallback;
    }

    $payload = [
        'model' => $config['ai']['model'] ?? 'gpt-4o-mini',
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Return only valid JSON with keys: headline, description_line_1, description_line_2. The headline must exactly follow this pattern: "<Main service> <City> | <USP> | <Call to action>". Keep the USP and call to action in the headline each max 30 characters. description_line_1 must mention the main service and include one or two USPs, max 90 characters. description_line_2 must include one more USP plus a call to action, max 90 characters. Do not use exclamation marks. Keep it realistic for a Google Search ad.',
            ],
            [
                'role' => 'user',
                'content' => "Business: " . ($business['business_name'] ?? '') . "\nWebsite: " . ($business['website'] ?? '') . "\nMain service: {$service}\nCity/service area: {$city}\nService/menu details: {$serviceDescription}\nUSPs/why choose: {$whyChoose}",
            ],
        ],
        'temperature' => 0.45,
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
        CURLOPT_TIMEOUT => 20,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status >= 400) {
        return $fallback;
    }

    $decoded = json_decode($body, true);
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    $ad = is_string($content) ? json_decode($content, true) : null;

    if (!is_array($ad)) {
        return $fallback;
    }

    return normalize_demo_ad_preview([
        'headline' => sanitize_ai_field($ad['headline'] ?? $fallback['headline'], 110),
        'description_line_1' => sanitize_ai_field($ad['description_line_1'] ?? $fallback['description_line_1'], 90),
        'description_line_2' => sanitize_ai_field($ad['description_line_2'] ?? $fallback['description_line_2'], 90),
    ], $fallback);
}

function fallback_demo_ad_preview(string $service, string $city, string $whyChoose): array
{
    $serviceTitle = ucwords(trim($service) !== '' && trim($service) !== 'service' ? trim($service) : 'Local Service');
    $cityTitle = $city !== '' && $city !== 'your area' ? ucwords(trim(preg_replace('/,.*/', '', $city))) : 'Near You';
    $uspLines = array_values(array_filter(array_map(static function (string $line): string {
        $line = preg_replace('/^[\s\-\*\x{2022}\d\.\)]+/u', '', trim($line)) ?? '';
        return trim($line);
    }, preg_split('/\r\n|\r|\n/', $whyChoose) ?: [])));

    if (empty($uspLines)) {
        $uspLines = ['Reliable local specialists', 'Fast friendly service', 'Get expert help today'];
    }

    $headlineUsp = demo_ad_limit_text($uspLines[0] ?? 'Trusted local team', 30);
    $cta = demo_ad_limit_text('Get a quote today', 30);
    $lineOneUsp = demo_ad_limit_text($uspLines[0] ?? 'reliable local support', 34);
    $lineTwoUsp = isset($uspLines[1]) ? demo_ad_limit_text($uspLines[1], 34) : '';

    return [
        'headline' => demo_ad_limit_text($serviceTitle . ' ' . $cityTitle, 30) . ' | ' . $headlineUsp . ' | ' . $cta,
        'description_line_1' => demo_ad_limit_text($serviceTitle . ' with ' . lcfirst($lineOneUsp) . ($lineTwoUsp !== '' ? ' and ' . lcfirst($lineTwoUsp) : '') . '.', 90),
        'description_line_2' => demo_ad_limit_text(($uspLines[2] ?? $uspLines[1] ?? 'Ready when you are') . '. ' . $cta . '.', 90),
    ];
}

function normalize_demo_ad_preview(array $ad, array $fallback): array
{
    $headline = trim((string) ($ad['headline'] ?? ''));
    $parts = array_map('trim', explode('|', $headline));

    if (count($parts) >= 3) {
        $headline = demo_ad_limit_text($parts[0], 30) . ' | ' . demo_ad_limit_text($parts[1], 30) . ' | ' . demo_ad_limit_text($parts[2], 30);
    } else {
        $headline = $fallback['headline'];
    }

    return [
        'headline' => $headline !== '' ? $headline : $fallback['headline'],
        'description_line_1' => demo_ad_limit_text((string) ($ad['description_line_1'] ?? $fallback['description_line_1']), 90),
        'description_line_2' => demo_ad_limit_text((string) ($ad['description_line_2'] ?? $fallback['description_line_2']), 90),
    ];
}

function demo_ad_limit_text(string $text, int $limit): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, max(1, $limit - 1)), " \t\n\r\0\x0B.,;:-") . '.';
}
