<?php

declare(strict_types=1);

const AGENCY_EMAIL = 'rick@theexperience.net.au';
const AGENCY_NAME = 'Rick';
const AGENCY_PASSWORD_HASH = '$2y$12$h9HzxIoLKXehQSxoiK60iuwV6eC6FLWnhau95pSWXUDTs0ZR7oKn2';
const AGENCY_WEBHOOK_URL = 'https://hook.us1.make.com/kh7h7r1ol6kf99vq2r78etv4d1tynggh';
const AGENCY_BRAND_ID = 2;
const AGENCY_BASE_URL = 'https://clients.firedtheagency.com';

function agency_config(): array
{
    $localConfigPath = APP_ROOT . '/app/config/local.php';

    if (!is_file($localConfigPath)) {
        return [];
    }

    $localConfig = require $localConfigPath;

    return isset($localConfig['backend']) && is_array($localConfig['backend']) ? $localConfig['backend'] : [];
}

function agency_login_email(): string
{
    $config = agency_config();
    $email = trim((string) ($config['emaillogin'] ?? ''));

    return $email !== '' ? $email : AGENCY_EMAIL;
}

function agency_login_name(): string
{
    $email = agency_login_email();

    return trim(strstr($email, '@', true) ?: AGENCY_NAME) ?: AGENCY_NAME;
}

function agency_is_logged_in(): bool
{
    return !empty($_SESSION['agency_user']) && ($_SESSION['agency_user']['email'] ?? '') === agency_login_email();
}

function require_agency_login(): void
{
    if (!agency_is_logged_in()) {
        view('agency/login', [
            'title' => 'Agency Login',
            'error' => '',
        ]);
        exit;
    }
}

function handle_agency_login(): void
{
    $login = strtolower(trim((string) ($_POST['login'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $email = agency_login_email();
    $name = agency_login_name();
    $plainPassword = (string) (agency_config()['passwordlogin'] ?? '');
    $isValidLogin = in_array($login, [strtolower($name), strtolower($email)], true);
    $isValidPassword = $plainPassword !== '' ? hash_equals($plainPassword, $password) : password_verify($password, AGENCY_PASSWORD_HASH);

    if (!$isValidLogin || !$isValidPassword) {
        view('agency/login', [
            'title' => 'Agency Login',
            'error' => 'Invalid agency login.',
        ]);
        exit;
    }

    $_SESSION['agency_user'] = [
        'name' => $name,
        'email' => $email,
    ];

    redirect('/index.php?page=agency');
}

function handle_agency_logout(): void
{
    unset($_SESSION['agency_user']);
    redirect('/index.php?page=agency');
}

function show_agency(): void
{
    require_agency_login();

    $leadId = (int) ($_GET['lead'] ?? 0);

    if ($leadId > 0) {
        show_agency_lead($leadId);
        return;
    }

    show_agency_leads();
}

function show_agency_leads(): void
{
    $connection = db();
    $stmt = $connection->prepare(
        'SELECT b.*, u.name AS user_name, u.email AS user_email, c.id AS campaign_id, c.campaign_name, c.status AS campaign_status, c.target_audience, c.goals, c.updated_at AS campaign_updated_at, c.created_at AS campaign_created_at, s.status AS subscription_status
        FROM businesses b
        LEFT JOIN users u ON u.business_id = b.id AND u.brand_id = b.brand_id
        LEFT JOIN campaigns c ON c.id = (
            SELECT c2.id FROM campaigns c2 WHERE c2.business_id = b.id ORDER BY c2.updated_at DESC, c2.created_at DESC, c2.id DESC LIMIT 1
        )
        LEFT JOIN subscriptions s ON s.id = (
            SELECT s2.id FROM subscriptions s2 WHERE s2.business_id = b.id ORDER BY s2.updated_at DESC, s2.created_at DESC, s2.id DESC LIMIT 1
        )
        WHERE b.brand_id = ?
        ORDER BY COALESCE(c.updated_at, b.updated_at, b.created_at) DESC, b.id DESC'
    );
    $brandId = AGENCY_BRAND_ID;
    $stmt->bind_param('i', $brandId);
    $stmt->execute();
    $leads = [];

    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $lead) {
        $lead['stage'] = agency_lead_stage($lead);
        $leads[] = $lead;
    }

    view('agency/list', [
        'title' => 'Agency Leads',
        'leads' => $leads,
    ]);
}

function show_agency_lead(int $leadId): void
{
    $connection = db();
    $businessStmt = $connection->prepare('SELECT * FROM businesses WHERE id = ? AND brand_id = ? LIMIT 1');
    $brandId = AGENCY_BRAND_ID;
    $businessStmt->bind_param('ii', $leadId, $brandId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc();

    if (!$business) {
        http_response_code(404);
        exit('Lead not found.');
    }

    $usersStmt = $connection->prepare('SELECT id, name, email, role, status, created_at, updated_at FROM users WHERE business_id = ? ORDER BY id ASC');
    $usersStmt->bind_param('i', $leadId);
    $usersStmt->execute();
    $users = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $campaignStmt = $connection->prepare('SELECT * FROM campaigns WHERE business_id = ? ORDER BY updated_at DESC, created_at DESC, id DESC');
    $campaignStmt->bind_param('i', $leadId);
    $campaignStmt->execute();
    $projects = [];

    foreach ($campaignStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $project) {
        $project['target_audience_data'] = json_decode($project['target_audience'] ?? '', true) ?: [];
        $project['goals_data'] = json_decode($project['goals'] ?? '', true) ?: [];
        $projects[] = $project;
    }

    $subscriptionStmt = $connection->prepare('SELECT * FROM subscriptions WHERE business_id = ? ORDER BY updated_at DESC, created_at DESC, id DESC');
    $subscriptionStmt->bind_param('i', $leadId);
    $subscriptionStmt->execute();
    $subscriptions = $subscriptionStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $latest = $projects[0] ?? [];
    $stageInput = array_merge($business, [
        'campaign_status' => $latest['status'] ?? '',
        'target_audience' => $latest['target_audience'] ?? '',
        'goals' => $latest['goals'] ?? '',
        'subscription_status' => $subscriptions[0]['status'] ?? '',
    ]);

    view('agency/detail', [
        'title' => 'Lead Detail',
        'business' => $business,
        'users' => $users,
        'projects' => $projects,
        'subscriptions' => $subscriptions,
        'stage' => agency_lead_stage($stageInput),
    ]);
}

function agency_lead_stage(array $lead): string
{
    $subscriptionStatus = strtolower((string) ($lead['subscription_status'] ?? ''));

    if (in_array($subscriptionStatus, ['active', 'trialing', 'test_paid'], true)) {
        return 'PAID';
    }

    $campaignStatus = strtolower((string) ($lead['campaign_status'] ?? ''));
    $targetAudience = json_decode((string) ($lead['target_audience'] ?? ''), true) ?: [];

    if ($campaignStatus === 'submitted') {
        return 'Step 3';
    }

    if (trim((string) ($targetAudience['service_description'] ?? '')) !== '') {
        return 'Step 2';
    }

    if (!empty($lead['campaign_id']) || trim((string) ($lead['website'] ?? '')) !== '' || trim((string) ($lead['email'] ?? '')) !== '') {
        return 'Step 1';
    }

    return 'Lead';
}

function agency_perth_time(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone('Australia/Perth'))->format('d M Y, g:i A') . ' AWST';
    } catch (Throwable $exception) {
        return $value;
    }
}

function notify_agency_lead_stage(array $business, string $stage): void
{
    $businessId = (int) ($business['id'] ?? 0);
    $website = (string) ($business['website'] ?? '');

    if ($businessId <= 0) {
        return;
    }

    $payload = [
        'subject' => 'new signup FiredTheAgency (stage ' . strtolower($stage) . ')',
        'email_subject' => 'new signup FiredTheAgency (stage ' . strtolower($stage) . ')',
        'brand' => 'FiredTheAgency',
        'brand_name' => 'FiredTheAgency',
        'agency' => 'FiredTheAgency',
        'business_name' => (string) ($business['business_name'] ?? ''),
        'email' => (string) ($business['email'] ?? ''),
        'domain' => $website,
        'domain_url' => $website,
        'website' => $website,
        'website_url' => $website,
        'business_website' => $website,
        'url' => $website,
        'Website' => $website,
        'stage' => $stage,
        'website_line' => 'Website: ' . ($website !== '' ? $website : 'Not supplied'),
        'domain_line' => 'Domain: ' . ($website !== '' ? $website : 'Not supplied'),
        'agency_link' => AGENCY_BASE_URL . '/index.php?page=agency&lead=' . $businessId,
    ];

    $json = json_encode($payload);

    if ($json === false) {
        return;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 4,
            'ignore_errors' => true,
        ],
    ]);

    try {
        @file_get_contents(AGENCY_WEBHOOK_URL, false, $context);
    } catch (Throwable $exception) {
    }
}
