<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_ROOT', dirname(__DIR__, 2));

require_once APP_ROOT . '/app/core/helpers.php';
require_once APP_ROOT . '/app/core/db.php';
require_once APP_ROOT . '/app/core/auth.php';
require_once APP_ROOT . '/app/services/PaymentService.php';
require_once APP_ROOT . '/app/services/StripeWebhookService.php';
require_once APP_ROOT . '/app/services/WebsiteInsightsService.php';
require_once APP_ROOT . '/app/services/TrackingService.php';
require_once APP_ROOT . '/app/controllers/SubscribeController.php';
require_once APP_ROOT . '/app/controllers/AuthController.php';
require_once APP_ROOT . '/app/controllers/DashboardController.php';
require_once APP_ROOT . '/app/controllers/AgencyController.php';

$appConfig = require APP_ROOT . '/app/config/app.php';
$brands = require APP_ROOT . '/app/config/brands.php';
