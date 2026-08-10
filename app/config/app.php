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
}

return $config;
