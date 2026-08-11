<?php

declare(strict_types=1);

function show_dashboard(array $brand): void
{
    require_auth($brand);

    $user = auth_user();
    $brandId = (int) $brand['id'];
    $connection = db();

    $businessStmt = $connection->prepare('SELECT * FROM businesses WHERE id = ? AND brand_id = ? LIMIT 1');
    $businessStmt->bind_param('ii', $user['business_id'], $brandId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc() ?: ['business_name' => 'Your Business'];
    $latestProject = find_latest_project($connection, (int) $user['business_id']);
    $activeSubscription = find_active_subscription($connection, (int) $user['business_id']);
    $draftProject = $activeSubscription ? null : find_draft_project($connection, (int) $user['business_id']);
    $budgetError = $_SESSION['budget_charge_error'] ?? '';
    $showTaxModal = !empty($_SESSION['show_tax_modal']) && $activeSubscription && empty($business['abn']);
    $showBudgetModal = !$showTaxModal
        && $activeSubscription
        && $latestProject
        && !has_charged_ad_spend($connection, (int) $user['business_id'])
        && (!empty($_SESSION['show_budget_modal']) || $budgetError !== '' || (!empty($_SESSION['show_tax_modal']) && !empty($business['abn'])));
    unset($_SESSION['show_tax_modal']);
    unset($_SESSION['show_budget_modal']);
    unset($_SESSION['budget_charge_error']);

    view('dashboard/index', [
        'brand' => $brand,
        'title' => 'Dashboard',
        'user' => $user,
        'business' => $business,
        'draftProject' => $draftProject,
        'latestProject' => $latestProject,
        'activeSubscription' => $activeSubscription,
        'showTaxModal' => $showTaxModal,
        'showBudgetModal' => $showBudgetModal,
        'budgetError' => $budgetError,
    ]);
}

function show_create_project(array $brand, array $errors = []): void
{
    require_auth($brand);

    $user = auth_user();
    $connection = db();
    $brandId = (int) $brand['id'];

    $businessStmt = $connection->prepare('SELECT * FROM businesses WHERE id = ? AND brand_id = ? LIMIT 1');
    $businessStmt->bind_param('ii', $user['business_id'], $brandId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc() ?: ['business_name' => 'Your Business'];
    $startNewProject = ($_GET['new'] ?? '') === '1';
    $draftProject = null;

    if ($startNewProject) {
        unset($_SESSION['draft_campaign_id']);
    } else {
        $draftProject = find_draft_project($connection, (int) $user['business_id']);
    }

    if ($draftProject) {
        $_SESSION['draft_campaign_id'] = (int) $draftProject['id'];
    }

    view('dashboard/create-project', [
        'brand' => $brand,
        'title' => 'Create New Project',
        'user' => $user,
        'business' => $business,
        'draftProject' => $draftProject,
        'errors' => $errors,
    ]);
}

function save_project(array $brand): void
{
    require_auth($brand);

    $user = auth_user();
    $campaignName = post_value('campaign_name');
    $businessName = post_value('business_name');
    $businessWebsite = post_value('business_website');
    $accountEmail = project_contact_email($user);
    $story = post_value('story');
    $whyChoose = post_value('why_choose');
    $serviceArea = post_value('service_area');
    $serviceShort = post_value('service_short');
    $serviceDescription = post_value('service_description');
    $sweetener = '';
    $exclusions = post_value('exclusions');
    $targetLat = post_value('target_lat');
    $targetLng = post_value('target_lng');
    $targetRadiusKm = (int) post_value('target_radius_km');
    $budget = (int) post_value('monthly_budget');

    $errors = [];

    if ($campaignName === '') {
        $errors[] = 'Project name is required.';
    }

    if ($businessName === '') {
        $errors[] = 'Business name is required.';
    }

    if ($businessWebsite === '') {
        $errors[] = 'Business URL or domain is required.';
    }

    if ($serviceShort === '' || $serviceDescription === '') {
        $errors[] = 'Service description is required.';
    }

    if ($budget < 50) {
        $errors[] = 'Monthly budget must be at least $50.';
    }

    if ($errors) {
        show_create_project($brand, $errors);
        return;
    }

    $connection = db();
    $connection->begin_transaction();

    try {
        $brandId = (int) $brand['id'];
        $businessId = (int) $user['business_id'];
        $userId = (int) $user['id'];
        $budgetCents = $budget * 100;
        $campaignType = 'google_ads';
        $status = 'submitted';
        $targetAudience = json_encode([
            'service_area' => $serviceArea,
            'service_short' => $serviceShort,
            'service_description' => $serviceDescription,
            'sweetener' => $sweetener,
            'exclusions' => $exclusions,
            'target_lat' => $targetLat,
            'target_lng' => $targetLng,
            'target_radius_km' => max(1, min(17, $targetRadiusKm)),
        ]);
        $goals = json_encode([
            'story' => $story,
            'why_choose' => $whyChoose,
        ]);

        $businessStmt = $connection->prepare('UPDATE businesses SET business_name = ?, website = ?, email = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?');
        $businessStmt->bind_param('sssii', $businessName, $businessWebsite, $accountEmail, $businessId, $brandId);
        $businessStmt->execute();

        if (!empty($_SESSION['demo_preview'])) {
            $userStatus = 'demo';
            $userStmt = $connection->prepare('UPDATE users SET name = ?, status = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?');
            $userStmt->bind_param('ssii', $businessName, $userStatus, $userId, $brandId);
            $userStmt->execute();

            $_SESSION['user']['name'] = $businessName;
        }

        $campaignId = (int) ($_SESSION['draft_campaign_id'] ?? 0);

        if ($campaignId > 0) {
            $campaignStmt = $connection->prepare(
                'UPDATE campaigns SET campaign_name = ?, campaign_type = ?, budget_cents = ?, target_location = ?, target_audience = ?, goals = ?, status = ?, updated_at = NOW() WHERE id = ? AND business_id = ?'
            );
            $campaignStmt->bind_param('ssissssii', $campaignName, $campaignType, $budgetCents, $serviceArea, $targetAudience, $goals, $status, $campaignId, $businessId);
            $campaignStmt->execute();
        } else {
            $campaignStmt = $connection->prepare(
                'INSERT INTO campaigns (business_id, campaign_name, campaign_type, budget_cents, target_location, target_audience, goals, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $campaignStmt->bind_param('ississss', $businessId, $campaignName, $campaignType, $budgetCents, $serviceArea, $targetAudience, $goals, $status);
            $campaignStmt->execute();
            $campaignId = $connection->insert_id;
        }

        $connection->commit();
        unset($_SESSION['draft_campaign_id']);
        $_SESSION['completed_campaign_id'] = $campaignId;
        $_SESSION['project_saved'] = true;

        redirect(brand_url($brand, 'project-complete'));
    } catch (mysqli_sql_exception $exception) {
        $connection->rollback();

        if ((int) $exception->getCode() === 1062) {
            show_create_project($brand, ['An account already exists for that email address. Please log in with that email instead.']);
            return;
        }

        throw $exception;
    }
}

function show_project_complete(array $brand): void
{
    require_auth($brand);

    $user = auth_user();
    $connection = db();
    $brandId = (int) $brand['id'];
    $businessId = (int) $user['business_id'];
    $campaignId = (int) ($_SESSION['completed_campaign_id'] ?? 0);

    $businessStmt = $connection->prepare('SELECT id, business_name FROM businesses WHERE id = ? AND brand_id = ? LIMIT 1');
    $businessStmt->bind_param('ii', $businessId, $brandId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc() ?: ['business_name' => 'Your Business'];

    if ($campaignId > 0) {
        $campaignStmt = $connection->prepare('SELECT * FROM campaigns WHERE id = ? AND business_id = ? LIMIT 1');
        $campaignStmt->bind_param('ii', $campaignId, $businessId);
    } else {
        $status = 'submitted';
        $campaignStmt = $connection->prepare('SELECT * FROM campaigns WHERE business_id = ? AND status = ? ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT 1');
        $campaignStmt->bind_param('is', $businessId, $status);
    }

    $campaignStmt->execute();
    $campaign = $campaignStmt->get_result()->fetch_assoc() ?: [
        'campaign_name' => 'Google Ads',
        'budget_cents' => 40000,
    ];
    $campaign['target_audience_data'] = json_decode($campaign['target_audience'] ?? '', true) ?: [];
    $campaign['goals_data'] = json_decode($campaign['goals'] ?? '', true) ?: [];
    $draftProject = find_draft_project($connection, $businessId);
    $keywords = generate_search_keywords($campaign, $business);

    view('dashboard/project-complete', [
        'brand' => $brand,
        'title' => 'Project Saved',
        'user' => $user,
        'business' => $business,
        'campaign' => $campaign,
        'keywords' => $keywords,
        'draftProject' => $draftProject,
    ]);
}

function start_stripe_checkout(array $brand): void
{
    require_auth($brand);

    $plan = post_value('plan');
    $acceptedTerms = post_value('accept_terms');

    if (!in_array($plan, ['standard', 'pro'], true) || $acceptedTerms !== '1') {
        redirect(brand_url($brand, 'project-complete') . '&checkout_error=terms');
    }

    $user = auth_user();
    $connection = db();
    $brandId = (int) $brand['id'];
    $businessId = (int) $user['business_id'];
    $campaignId = (int) ($_SESSION['completed_campaign_id'] ?? 0);

    $businessStmt = $connection->prepare('SELECT id, business_name FROM businesses WHERE id = ? AND brand_id = ? LIMIT 1');
    $businessStmt->bind_param('ii', $businessId, $brandId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc();

    if (!$business) {
        redirect(brand_url($brand, 'dashboard'));
    }

    if ($campaignId > 0) {
        $campaignStmt = $connection->prepare('SELECT * FROM campaigns WHERE id = ? AND business_id = ? LIMIT 1');
        $campaignStmt->bind_param('ii', $campaignId, $businessId);
    } else {
        $status = 'submitted';
        $campaignStmt = $connection->prepare('SELECT * FROM campaigns WHERE business_id = ? AND status = ? ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT 1');
        $campaignStmt->bind_param('is', $businessId, $status);
    }

    $campaignStmt->execute();
    $campaign = $campaignStmt->get_result()->fetch_assoc();

    if (!$campaign) {
        redirect(brand_url($brand, 'create-project'));
    }

    $checkout = create_stripe_checkout_session($brand, $user, $business, $campaign, $plan);

    if (!$checkout['ok']) {
        $_SESSION['checkout_error'] = $checkout['message'];
        redirect(brand_url($brand, 'project-complete') . '&checkout_error=stripe');
    }

    $subscriptionStatus = 'checkout_started';
    $planName = $plan === 'pro' ? 'Premium Solution' : 'Smart Choice';
    $amountCents = $plan === 'pro' ? 13900 : 6700;
    $currency = 'aud';

    $stmt = $connection->prepare(
        'INSERT INTO subscriptions (brand_id, business_id, user_id, status, plan_name, amount_cents, currency, stripe_checkout_session_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('iiississ', $brandId, $businessId, $user['id'], $subscriptionStatus, $planName, $amountCents, $currency, $checkout['id']);
    $stmt->execute();

    redirect($checkout['url']);
}

function handle_checkout_success(array $brand): void
{
    require_auth($brand);

    $sessionId = post_value('session_id') ?: ($_GET['session_id'] ?? '');

    if ($sessionId === '') {
        redirect(brand_url($brand, 'dashboard'));
    }

    $session = retrieve_stripe_checkout_session($sessionId);

    if (!$session || ($session->status ?? '') !== 'complete') {
        $_SESSION['checkout_error'] = 'Stripe could not confirm this payment yet. Please try again.';
        redirect(brand_url($brand, 'project-complete') . '&checkout_error=stripe');
    }

    $connection = db();
    $user = auth_user();
    $businessId = (int) $user['business_id'];

    $status = 'active';
    $stripeCustomerId = !empty($session->customer) ? (string) $session->customer : null;
    $stripeSubscriptionId = !empty($session->subscription) ? (string) $session->subscription : null;

    $stmt = $connection->prepare(
        'UPDATE subscriptions SET status = ?, stripe_customer_id = COALESCE(?, stripe_customer_id), stripe_subscription_id = COALESCE(?, stripe_subscription_id), updated_at = NOW() WHERE business_id = ? AND stripe_checkout_session_id = ?'
    );
    $stmt->bind_param('sssis', $status, $stripeCustomerId, $stripeSubscriptionId, $businessId, $sessionId);
    $stmt->execute();

    $businessStmt = $connection->prepare('SELECT * FROM businesses WHERE id = ? LIMIT 1');
    $businessStmt->bind_param('i', $businessId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc();

    if ($business) {
        notify_agency_lead_stage($business, 'PAID');
    }

    $_SESSION['show_tax_modal'] = true;

    redirect(brand_url($brand, 'dashboard') . '&checkout=success');
}

function save_tax_details(array $brand): void
{
    require_auth($brand);

    $user = auth_user();
    $connection = db();
    $businessId = (int) $user['business_id'];
    $brandId = (int) $brand['id'];
    $officialName = post_value('official_business_name');
    $abn = post_value('abn');
    $address = post_value('business_address');
    $phone = post_value('phone');

    $stmt = $connection->prepare('UPDATE businesses SET official_business_name = ?, abn = ?, business_address = ?, phone = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?');
    $stmt->bind_param('ssssii', $officialName, $abn, $address, $phone, $businessId, $brandId);
    $stmt->execute();

    $_SESSION['show_budget_modal'] = true;

    redirect(brand_url($brand, 'dashboard'));
}

function confirm_ad_spend_budget(array $brand): void
{
    require_auth($brand);

    $user = auth_user();
    $connection = db();
    $brandId = (int) $brand['id'];
    $businessId = (int) $user['business_id'];
    $budget = (int) post_value('monthly_budget');

    if ($budget < 50) {
        $_SESSION['budget_charge_error'] = 'Please choose a monthly ad spend package of at least $50.';
        $_SESSION['show_budget_modal'] = true;
        redirect(brand_url($brand, 'dashboard'));
    }

    $activeSubscription = find_active_subscription($connection, $businessId);
    $campaign = find_latest_project($connection, $businessId);

    if (!$activeSubscription || !$campaign) {
        redirect(brand_url($brand, 'dashboard'));
    }

    $newBudgetCents = $budget * 100;
    $charge = charge_monthly_ad_spend_package($brand, $activeSubscription, $campaign, $newBudgetCents);

    if (!$charge['ok']) {
        pause_subscription_and_projects(
            $activeSubscription['stripe_subscription_id'] ?? '',
            $activeSubscription['stripe_customer_id'] ?? '',
            (int) $campaign['id'],
            'monthly ad spend package payment failed'
        );
        $_SESSION['budget_charge_error'] = 'Could not charge the monthly ad spend package: ' . $charge['message'];
        $_SESSION['show_budget_modal'] = true;
        redirect(brand_url($brand, 'dashboard'));
    }

    $connection->begin_transaction();

    try {
        $campaignStmt = $connection->prepare('UPDATE campaigns SET budget_cents = ?, updated_at = NOW() WHERE id = ? AND business_id = ?');
        $campaignStmt->bind_param('iii', $newBudgetCents, $campaign['id'], $businessId);
        $campaignStmt->execute();

        $status = 'charged';
        $notes = 'Stripe payment intent: ' . ($charge['payment_intent_id'] ?? '')
            . '; charged including GST: $' . number_format((float) (($charge['charged_amount_cents'] ?? $newBudgetCents) / 100), 2);
        $stripeSubscriptionId = $activeSubscription['stripe_subscription_id'] ?? null;

        $requestStmt = $connection->prepare(
            'INSERT INTO budget_change_requests (brand_id, business_id, campaign_id, user_id, requested_budget_cents, status, stripe_subscription_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $requestStmt->bind_param('iiiiisss', $brandId, $businessId, $campaign['id'], $user['id'], $newBudgetCents, $status, $stripeSubscriptionId, $notes);
        $requestStmt->execute();

        $connection->commit();
    } catch (mysqli_sql_exception $exception) {
        $connection->rollback();
        throw $exception;
    }

    redirect(brand_url($brand, 'dashboard'));
}

function show_profile(array $brand, array $errors = []): void
{
    require_auth($brand);

    $user = auth_user();
    $connection = db();
    $brandId = (int) $brand['id'];

    $businessStmt = $connection->prepare('SELECT * FROM businesses WHERE id = ? AND brand_id = ? LIMIT 1');
    $businessStmt->bind_param('ii', $user['business_id'], $brandId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc() ?: [];
    $activeSubscription = find_active_subscription($connection, (int) $user['business_id']);
    $latestProject = find_latest_project($connection, (int) $user['business_id']);

    view('dashboard/profile', [
        'brand' => $brand,
        'title' => 'Profile',
        'user' => $user,
        'business' => $business,
        'activeSubscription' => $activeSubscription,
        'latestProject' => $latestProject,
        'errors' => $errors,
    ]);
}

function show_budget(array $brand): void
{
    require_auth($brand);

    $user = auth_user();
    $connection = db();
    $brandId = (int) $brand['id'];

    $businessStmt = $connection->prepare('SELECT * FROM businesses WHERE id = ? AND brand_id = ? LIMIT 1');
    $businessStmt->bind_param('ii', $user['business_id'], $brandId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc() ?: [];
    $activeSubscription = find_active_subscription($connection, (int) $user['business_id']);
    $latestProject = find_latest_project($connection, (int) $user['business_id']);

    view('dashboard/budget', [
        'brand' => $brand,
        'title' => 'Your Budget',
        'user' => $user,
        'business' => $business,
        'activeSubscription' => $activeSubscription,
        'latestProject' => $latestProject,
    ]);
}

function save_profile(array $brand): void
{
    require_auth($brand);

    $user = auth_user();
    $connection = db();
    $brandId = (int) $brand['id'];
    $businessId = (int) $user['business_id'];
    $name = post_value('name');
    $email = post_value('email');
    $officialName = post_value('official_business_name');
    $abn = post_value('abn');
    $address = post_value('business_address');
    $phone = post_value('phone');
    $password = post_value('password');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        show_profile($brand, ['Name and a valid email address are required.']);
        return;
    }

    $connection->begin_transaction();

    try {
        if ($password !== '') {
            if (strlen($password) < 8) {
                $connection->rollback();
                show_profile($brand, ['Password must be at least 8 characters.']);
                return;
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $userStmt = $connection->prepare('UPDATE users SET name = ?, email = ?, password_hash = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?');
            $userStmt->bind_param('sssii', $name, $email, $passwordHash, $user['id'], $brandId);
        } else {
            $userStmt = $connection->prepare('UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?');
            $userStmt->bind_param('ssii', $name, $email, $user['id'], $brandId);
        }

        $userStmt->execute();

        $businessStmt = $connection->prepare('UPDATE businesses SET official_business_name = ?, abn = ?, business_address = ?, phone = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?');
        $businessStmt->bind_param('ssssii', $officialName, $abn, $address, $phone, $businessId, $brandId);
        $businessStmt->execute();

        $connection->commit();

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        redirect(brand_url($brand, 'profile') . '&saved=1');
    } catch (mysqli_sql_exception $exception) {
        $connection->rollback();
        show_profile($brand, ['Could not save your profile.']);
    }
}

function show_invoices(array $brand): void
{
    require_auth($brand);

    $user = auth_user();
    $connection = db();
    $brandId = (int) $brand['id'];

    $businessStmt = $connection->prepare('SELECT * FROM businesses WHERE id = ? AND brand_id = ? LIMIT 1');
    $businessStmt->bind_param('ii', $user['business_id'], $brandId);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc() ?: [];
    $activeSubscription = find_active_subscription($connection, (int) $user['business_id']);
    $latestProject = find_latest_project($connection, (int) $user['business_id']);

    view('dashboard/invoices', [
        'brand' => $brand,
        'title' => 'Invoices',
        'user' => $user,
        'business' => $business,
        'activeSubscription' => $activeSubscription,
        'latestProject' => $latestProject,
    ]);
}

function show_terms(array $brand): void
{
    view('public/terms', [
        'brand' => $brand,
        'title' => 'Terms and Conditions',
    ]);
}

function show_thank_you(array $brand): void
{
    view('public/thank-you', [
        'brand' => $brand,
        'title' => 'Thank You',
    ]);
}

function find_active_subscription(mysqli $connection, int $businessId): ?array
{
    $statuses = ['active', 'trialing'];
    $stmt = $connection->prepare('SELECT * FROM subscriptions WHERE business_id = ? AND status IN (?, ?) ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT 1');
    $stmt->bind_param('iss', $businessId, $statuses[0], $statuses[1]);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();

    return $subscription ?: null;
}

function find_latest_project(mysqli $connection, int $businessId): ?array
{
    $stmt = $connection->prepare(
        'SELECT * FROM campaigns WHERE business_id = ? ORDER BY CASE status WHEN "submitted" THEN 0 WHEN "draft" THEN 1 ELSE 2 END, updated_at DESC, created_at DESC, id DESC LIMIT 1'
    );
    $stmt->bind_param('i', $businessId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();

    if (!$project) {
        return null;
    }

    $project['target_audience_data'] = json_decode($project['target_audience'] ?? '', true) ?: [];
    $project['goals_data'] = json_decode($project['goals'] ?? '', true) ?: [];

    return $project;
}

function project_service_label(?array $project, string $fallback): string
{
    if (!$project) {
        return $fallback;
    }

    $targetAudience = $project['target_audience_data'] ?? json_decode($project['target_audience'] ?? '', true) ?: [];
    $service = trim((string) ($targetAudience['service_short'] ?? ''));

    return $service !== '' ? $service : $fallback;
}

function has_charged_ad_spend(mysqli $connection, int $businessId): bool
{
    $status = 'charged';
    $stmt = $connection->prepare('SELECT id FROM budget_change_requests WHERE business_id = ? AND status = ? LIMIT 1');
    $stmt->bind_param('is', $businessId, $status);
    $stmt->execute();

    return (bool) $stmt->get_result()->fetch_assoc();
}

function save_project_step(array $brand): void
{
    require_auth($brand);

    header('Content-Type: application/json');

    $user = auth_user();
    $step = (int) post_value('step');
    $businessId = (int) $user['business_id'];
    $brandId = (int) $brand['id'];
    $campaignId = (int) ($_SESSION['draft_campaign_id'] ?? 0);
    $connection = db();

    $campaignName = post_value('campaign_name') ?: 'Google Ads';
    $campaignType = 'google_ads';
    $status = $step >= 3 ? 'submitted' : 'draft';
    $budget = (int) post_value('monthly_budget');
    $budgetCents = $budget > 0 ? $budget * 100 : null;
    $businessName = post_value('business_name');
    $businessWebsite = post_value('business_website');
    $accountEmail = project_contact_email($user);
    $serviceArea = post_value('service_area');
    $targetRadiusKm = (int) post_value('target_radius_km');

    $errors = [];

    if ($step === 1) {
        if ($businessName === '') {
            $errors[] = 'Business name is required.';
        }

        if ($businessWebsite === '') {
            $errors[] = 'Business URL or domain is required.';
        }

    }

    if ($errors) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => implode(' ', $errors),
        ]);
        exit;
    }

    $targetAudience = json_encode([
        'service_area' => $serviceArea,
        'service_short' => post_value('service_short'),
        'service_description' => post_value('service_description'),
        'sweetener' => '',
        'exclusions' => post_value('exclusions'),
        'target_lat' => post_value('target_lat'),
        'target_lng' => post_value('target_lng'),
        'target_radius_km' => max(1, min(17, $targetRadiusKm)),
    ]);
    $goals = json_encode([
        'story' => post_value('story'),
        'why_choose' => post_value('why_choose'),
    ]);

    $connection->begin_transaction();

    try {
        if ($step === 1 && !empty($_SESSION['demo_preview'])) {
            $existingStmt = $connection->prepare('SELECT id, brand_id, business_id, name, email, status FROM users WHERE brand_id = ? AND email = ? LIMIT 1');
            $existingStmt->bind_param('is', $brandId, $accountEmail);
            $existingStmt->execute();
            $existingUser = $existingStmt->get_result()->fetch_assoc();

            if ($existingUser && (int) $existingUser['id'] !== (int) $user['id']) {
                if (($existingUser['status'] ?? '') !== 'demo') {
                    $connection->rollback();
                    http_response_code(422);
                    echo json_encode([
                        'ok' => false,
                        'message' => 'An account already exists for that email address. Please log in with that email instead.',
                    ]);
                    exit;
                }

                $user = $existingUser;
                $businessId = (int) $existingUser['business_id'];
                $_SESSION['user'] = [
                    'id' => (int) $existingUser['id'],
                    'brand_id' => (int) $existingUser['brand_id'],
                    'business_id' => (int) $existingUser['business_id'],
                    'name' => $existingUser['name'],
                    'email' => $existingUser['email'],
                ];
                unset($_SESSION['draft_campaign_id']);
                $campaignId = 0;
            }
        }

        if ($businessName !== '') {
            $businessStmt = $connection->prepare('UPDATE businesses SET business_name = ?, website = ?, email = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?');
            $businessStmt->bind_param('sssii', $businessName, $businessWebsite, $accountEmail, $businessId, $brandId);
            $businessStmt->execute();
        }

        if ($step === 1 && !empty($_SESSION['demo_preview'])) {
            $userStatus = 'demo';
            $userStmt = $connection->prepare('UPDATE users SET name = ?, email = ?, status = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?');
            $userStmt->bind_param('sssii', $businessName, $accountEmail, $userStatus, $user['id'], $brandId);
            $userStmt->execute();

            $_SESSION['user']['name'] = $businessName;
            $_SESSION['user']['email'] = $accountEmail;
        }

        if ($campaignId > 0) {
            $campaignStmt = $connection->prepare(
                'UPDATE campaigns SET campaign_name = ?, campaign_type = ?, budget_cents = ?, target_location = ?, target_audience = ?, goals = ?, status = ?, updated_at = NOW() WHERE id = ? AND business_id = ?'
            );
            $campaignStmt->bind_param('ssissssii', $campaignName, $campaignType, $budgetCents, $serviceArea, $targetAudience, $goals, $status, $campaignId, $businessId);
            $campaignStmt->execute();
        } else {
            $campaignStmt = $connection->prepare(
                'INSERT INTO campaigns (business_id, campaign_name, campaign_type, budget_cents, target_location, target_audience, goals, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $campaignStmt->bind_param('ississss', $businessId, $campaignName, $campaignType, $budgetCents, $serviceArea, $targetAudience, $goals, $status);
            $campaignStmt->execute();
            $campaignId = $connection->insert_id;
            $_SESSION['draft_campaign_id'] = $campaignId;
        }

        $connection->commit();

        if ($step === 1) {
            $webhookKey = 'agency_webhook_step1_sent_' . $businessId;

            if (empty($_SESSION[$webhookKey])) {
                notify_agency_lead_stage([
                    'id' => $businessId,
                    'business_name' => $businessName,
                    'email' => $accountEmail,
                    'website' => $businessWebsite,
                ], 'Step 1');
                $_SESSION[$webhookKey] = true;
            }
        }

        echo json_encode([
            'ok' => true,
            'campaign_id' => $campaignId,
            'status' => $status,
        ]);
    } catch (mysqli_sql_exception $exception) {
        $connection->rollback();

        if ((int) $exception->getCode() === 1062) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'An account already exists for that email address. Please log in with that email instead.',
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Could not save this step.',
        ]);
    }

    exit;
}

function find_draft_project(mysqli $connection, int $businessId): ?array
{
    $draftStatus = 'draft';
    $submittedStatus = 'submitted';
    $stmt = $connection->prepare('SELECT * FROM campaigns WHERE business_id = ? AND status IN (?, ?) ORDER BY CASE status WHEN "draft" THEN 0 WHEN "submitted" THEN 1 ELSE 2 END, updated_at DESC, created_at DESC, id DESC LIMIT 1');
    $stmt->bind_param('iss', $businessId, $draftStatus, $submittedStatus);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();

    if (!$project) {
        return null;
    }

    $project['target_audience_data'] = json_decode($project['target_audience'] ?? '', true) ?: [];
    $project['goals_data'] = json_decode($project['goals'] ?? '', true) ?: [];

    return $project;
}

function project_contact_email(array $user): string
{
    $email = trim((string) ($user['email'] ?? ''));

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'demo@firedtheagency.com';
}
