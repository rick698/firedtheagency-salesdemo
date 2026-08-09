<?php

declare(strict_types=1);

function handle_stripe_webhook(array $brands): void
{
    $config = require APP_ROOT . '/app/config/app.php';
    $secretKey = $config['stripe']['secret_key'] ?? '';
    $webhookSecret = $config['stripe']['webhook_secret'] ?? '';
    $stripeInit = APP_ROOT . '/app/vendor/stripe-php-19.3.0/init.php';

    if ($secretKey === '' || $webhookSecret === '' || !is_file($stripeInit)) {
        http_response_code(500);
        echo 'Stripe webhook is not configured.';
        return;
    }

    require_once $stripeInit;
    \Stripe\Stripe::setApiKey($secretKey);

    $payload = file_get_contents('php://input') ?: '';
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
    } catch (\Throwable $exception) {
        http_response_code(400);
        echo 'Invalid Stripe webhook.';
        return;
    }

    if (stripe_event_seen($event->id)) {
        http_response_code(200);
        echo 'Already handled.';
        return;
    }

    $status = 'ignored';

    try {
        $status = process_stripe_event($event, $brands);
        record_stripe_event($event, $payload, $status);
    } catch (\Throwable $exception) {
        record_stripe_event($event, $payload, 'error: ' . $exception->getMessage());
        throw $exception;
    }

    http_response_code(200);
    echo $status;
}

function process_stripe_event(object $event, array $brands): string
{
    $object = $event->data->object ?? null;

    if (!$object) {
        return 'ignored';
    }

    return match ($event->type) {
        'checkout.session.completed' => activate_subscription_from_checkout($object),
        'invoice.payment_failed' => pause_from_invoice_failure($object, 'management fee payment failed'),
        'customer.subscription.deleted',
        'customer.subscription.paused',
        'customer.subscription.updated' => sync_subscription_status_from_stripe($object),
        'payment_intent.payment_failed' => pause_from_payment_intent_failure($object, 'monthly ad spend package payment failed'),
        default => 'ignored',
    };
}

function activate_subscription_from_checkout(object $session): string
{
    if (($session->status ?? '') !== 'complete' || empty($session->id)) {
        return 'ignored';
    }

    $connection = db();
    $status = 'active';
    $stripeCustomerId = !empty($session->customer) ? (string) $session->customer : null;
    $stripeSubscriptionId = !empty($session->subscription) ? (string) $session->subscription : null;
    $sessionId = (string) $session->id;

    $stmt = $connection->prepare(
        'UPDATE subscriptions SET status = ?, stripe_customer_id = COALESCE(?, stripe_customer_id), stripe_subscription_id = COALESCE(?, stripe_subscription_id), updated_at = NOW() WHERE stripe_checkout_session_id = ?'
    );
    $stmt->bind_param('ssss', $status, $stripeCustomerId, $stripeSubscriptionId, $sessionId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $businessStmt = $connection->prepare(
            'SELECT b.* FROM businesses b INNER JOIN subscriptions s ON s.business_id = b.id WHERE s.stripe_checkout_session_id = ? LIMIT 1'
        );
        $businessStmt->bind_param('s', $sessionId);
        $businessStmt->execute();
        $business = $businessStmt->get_result()->fetch_assoc();

        if ($business) {
            notify_agency_lead_stage($business, 'PAID');
        }
    }

    return $stmt->affected_rows > 0 ? 'subscription activated' : 'no subscription matched';
}

function pause_from_invoice_failure(object $invoice, string $reason): string
{
    $stripeSubscriptionId = !empty($invoice->subscription) ? (string) $invoice->subscription : '';
    $stripeCustomerId = !empty($invoice->customer) ? (string) $invoice->customer : '';

    return pause_subscription_and_projects($stripeSubscriptionId, $stripeCustomerId, null, $reason);
}

function pause_from_payment_intent_failure(object $paymentIntent, string $reason): string
{
    $metadata = $paymentIntent->metadata ?? null;
    $campaignId = isset($metadata->campaign_id) ? (int) $metadata->campaign_id : null;
    $stripeCustomerId = !empty($paymentIntent->customer) ? (string) $paymentIntent->customer : '';

    return pause_subscription_and_projects('', $stripeCustomerId, $campaignId, $reason);
}

function sync_subscription_status_from_stripe(object $stripeSubscription): string
{
    $stripeSubscriptionId = !empty($stripeSubscription->id) ? (string) $stripeSubscription->id : '';

    if ($stripeSubscriptionId === '') {
        return 'ignored';
    }

    $stripeStatus = (string) ($stripeSubscription->status ?? '');
    $pausedStatuses = ['canceled', 'incomplete_expired', 'past_due', 'paused', 'unpaid'];

    if (in_array($stripeStatus, $pausedStatuses, true)) {
        return pause_subscription_and_projects($stripeSubscriptionId, '', null, 'subscription status: ' . $stripeStatus);
    }

    if (in_array($stripeStatus, ['active', 'trialing'], true)) {
        $connection = db();
        $status = $stripeStatus;
        $stmt = $connection->prepare('UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE stripe_subscription_id = ?');
        $stmt->bind_param('ss', $status, $stripeSubscriptionId);
        $stmt->execute();

        return 'subscription marked ' . $stripeStatus;
    }

    return 'ignored';
}

function pause_subscription_and_projects(string $stripeSubscriptionId, string $stripeCustomerId, ?int $campaignId, string $reason): string
{
    $connection = db();
    $subscription = null;

    if ($stripeSubscriptionId !== '') {
        $stmt = $connection->prepare('SELECT * FROM subscriptions WHERE stripe_subscription_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('s', $stripeSubscriptionId);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc() ?: null;
    }

    if (!$subscription && $stripeCustomerId !== '') {
        $stmt = $connection->prepare('SELECT * FROM subscriptions WHERE stripe_customer_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('s', $stripeCustomerId);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc() ?: null;
    }

    if (!$subscription && $campaignId) {
        $stmt = $connection->prepare('SELECT s.* FROM subscriptions s INNER JOIN campaigns c ON c.business_id = s.business_id WHERE c.id = ? ORDER BY s.id DESC LIMIT 1');
        $stmt->bind_param('i', $campaignId);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc() ?: null;
    }

    if (!$subscription) {
        return 'no subscription matched for pause';
    }

    $connection->begin_transaction();

    try {
        $paused = 'paused';
        $subscriptionId = (int) $subscription['id'];
        $businessId = (int) $subscription['business_id'];
        $note = trim(($subscription['plan_name'] ?? 'Subscription') . ' - paused: ' . $reason);

        $subscriptionStmt = $connection->prepare('UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ?');
        $subscriptionStmt->bind_param('si', $paused, $subscriptionId);
        $subscriptionStmt->execute();

        $campaignStmt = $connection->prepare('UPDATE campaigns SET status = ?, updated_at = NOW() WHERE business_id = ? AND status <> ?');
        $campaignStmt->bind_param('sis', $paused, $businessId, $paused);
        $campaignStmt->execute();

        $requestStmt = $connection->prepare(
            'INSERT INTO budget_change_requests (brand_id, business_id, campaign_id, user_id, requested_budget_cents, status, stripe_subscription_id, notes, created_at) VALUES (?, ?, NULL, ?, 0, ?, ?, ?, NOW())'
        );
        $brandId = (int) $subscription['brand_id'];
        $userId = (int) $subscription['user_id'];
        $requestStatus = 'payment_failed';
        $matchedStripeSubscriptionId = $subscription['stripe_subscription_id'] ?? null;
        $requestStmt->bind_param('iiisss', $brandId, $businessId, $userId, $requestStatus, $matchedStripeSubscriptionId, $note);
        $requestStmt->execute();

        $connection->commit();
    } catch (mysqli_sql_exception $exception) {
        $connection->rollback();
        throw $exception;
    }

    return 'project paused';
}

function stripe_event_seen(string $eventId): bool
{
    $connection = db();
    $stmt = $connection->prepare('SELECT id FROM stripe_events WHERE event_id = ? LIMIT 1');
    $stmt->bind_param('s', $eventId);
    $stmt->execute();

    return (bool) $stmt->get_result()->fetch_assoc();
}

function record_stripe_event(object $event, string $payload, string $status): void
{
    $connection = db();
    $eventId = (string) $event->id;
    $eventType = (string) $event->type;

    $stmt = $connection->prepare(
        'INSERT INTO stripe_events (event_id, event_type, handling_status, payload, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE handling_status = VALUES(handling_status), payload = VALUES(payload)'
    );
    $stmt->bind_param('ssss', $eventId, $eventType, $status, $payload);
    $stmt->execute();
}
