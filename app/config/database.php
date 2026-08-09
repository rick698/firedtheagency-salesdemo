<?php

$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'smallbusinessdigitalservices',
    'port' => 3306,
    'charset' => 'utf8mb4',
];

$localConfigPath = __DIR__ . '/local.php';

if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (isset($localConfig['database']) && is_array($localConfig['database'])) {
        $config = array_merge($config, $localConfig['database']);
    }
}

return $config;
