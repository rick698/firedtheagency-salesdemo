<?php

$config = [
    'app_name' => 'Client Campaign Portal',
    'environment' => 'local',
    'base_path' => dirname(__DIR__, 2),
    'stripe' => [
        'publishable_key' => '',
        'secret_key' => '',
        'webhook_secret' => '',
        'price_id' => '',
        'currency' => 'aud',
    ],
    'ai' => [
        'openai_api_key' => '',
        'model' => 'gpt-4o-mini',
    ],
    'tracking' => [
        'ga4_measurement_id' => '',
        'meta_pixel_id' => '',
        'meta_capi_token' => '',
        'meta_test_event_code' => '',
        'meta_graph_version' => 'v25.0',
    ],
];

$localConfigPath = __DIR__ . '/local.php';

if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (isset($localConfig['app']) && is_array($localConfig['app'])) {
        $config = array_replace_recursive($config, $localConfig['app']);
    }

    if (isset($localConfig['stripe']) && is_array($localConfig['stripe'])) {
        $config['stripe'] = array_merge($config['stripe'], $localConfig['stripe']);
    }

    if (isset($localConfig['ai']) && is_array($localConfig['ai'])) {
        $config['ai'] = array_merge($config['ai'], $localConfig['ai']);
    }

    if (isset($localConfig['tracking']) && is_array($localConfig['tracking'])) {
        $config['tracking'] = array_merge($config['tracking'], $localConfig['tracking']);
    }
}

return $config;
