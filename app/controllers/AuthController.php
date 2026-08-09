<?php

declare(strict_types=1);

function show_register(array $brand, array $errors = []): void
{
    view('auth/register', [
        'brand' => $brand,
        'title' => 'Create Account',
        'errors' => $errors,
    ]);
}

function register_user(array $brand): void
{
    $brandId = (int) $brand['id'];
    $name = post_value('name');
    $email = post_value('email');
    $password = post_value('password');
    $businessName = post_value('business_name');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Your name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($businessName === '') {
        $errors[] = 'Business name is required.';
    }

    if ($errors) {
        show_register($brand, $errors);
        return;
    }

    $connection = db();
    $connection->begin_transaction();

    try {
        $businessStmt = $connection->prepare(
            'INSERT INTO businesses (brand_id, business_name, contact_name, email, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $businessStmt->bind_param('isss', $brandId, $businessName, $name, $email);
        $businessStmt->execute();
        $businessId = $connection->insert_id;

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'owner';
        $status = 'active';

        $userStmt = $connection->prepare(
            'INSERT INTO users (brand_id, business_id, name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $userStmt->bind_param('iisssss', $brandId, $businessId, $name, $email, $passwordHash, $role, $status);
        $userStmt->execute();
        $userId = $connection->insert_id;

        $subscriptionStatus = 'test_paid';
        $planName = 'Launch Campaign';
        $amountCents = 9900;
        $currency = 'aud';

        $subscriptionStmt = $connection->prepare(
            'INSERT INTO subscriptions (brand_id, business_id, user_id, status, plan_name, amount_cents, currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $subscriptionStmt->bind_param('iiissis', $brandId, $businessId, $userId, $subscriptionStatus, $planName, $amountCents, $currency);
        $subscriptionStmt->execute();

        $connection->commit();

        login_user([
            'id' => $userId,
            'brand_id' => $brand['id'],
            'business_id' => $businessId,
            'name' => $name,
            'email' => $email,
        ]);

        unset($_SESSION['pending_subscription']);
        redirect(brand_url($brand, 'dashboard'));
    } catch (mysqli_sql_exception $exception) {
        $connection->rollback();

        if ((int) $exception->getCode() === 1062) {
            show_register($brand, ['An account already exists for that email address.']);
            return;
        }

        throw $exception;
    }
}

function show_login(array $brand, array $errors = []): void
{
    view('auth/login', [
        'brand' => $brand,
        'title' => 'Login',
        'errors' => $errors,
    ]);
}

function login_with_password(array $brand): void
{
    $brandId = (int) $brand['id'];
    $email = post_value('email');
    $password = post_value('password');

    $connection = db();
    $stmt = $connection->prepare('SELECT id, brand_id, business_id, name, email, password_hash FROM users WHERE brand_id = ? AND email = ? LIMIT 1');
    $stmt->bind_param('is', $brandId, $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        show_login($brand, ['Invalid email or password.']);
        return;
    }

    login_user($user);
    redirect(brand_url($brand, 'dashboard'));
}

function start_demo_dashboard(array $brand): void
{
    $brandId = (int) $brand['id'];
    $connection = db();
    $sessionId = session_id() ?: bin2hex(random_bytes(8));
    $demoEmail = 'demo+' . preg_replace('/[^a-zA-Z0-9]/', '', $sessionId) . '@smallbusinessdigitalservices.com.au';
    $demoName = 'Demo Visitor';
    $businessName = 'Your Business';
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $role = 'owner';
    $status = 'demo';

    $existingStmt = $connection->prepare('SELECT id, brand_id, business_id, name, email FROM users WHERE brand_id = ? AND email = ? LIMIT 1');
    $existingStmt->bind_param('is', $brandId, $demoEmail);
    $existingStmt->execute();
    $existingUser = $existingStmt->get_result()->fetch_assoc();

    if ($existingUser) {
        login_user($existingUser);
        $_SESSION['demo_preview'] = true;
        redirect(brand_url($brand, 'dashboard'));
    }

    $connection->begin_transaction();

    try {
        $businessStmt = $connection->prepare(
            'INSERT INTO businesses (brand_id, business_name, contact_name, email, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $businessStmt->bind_param('isss', $brandId, $businessName, $demoName, $demoEmail);
        $businessStmt->execute();
        $businessId = $connection->insert_id;

        $userStmt = $connection->prepare(
            'INSERT INTO users (brand_id, business_id, name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $userStmt->bind_param('iisssss', $brandId, $businessId, $demoName, $demoEmail, $passwordHash, $role, $status);
        $userStmt->execute();
        $userId = $connection->insert_id;

        $connection->commit();

        login_user([
            'id' => $userId,
            'brand_id' => $brand['id'],
            'business_id' => $businessId,
            'name' => $demoName,
            'email' => $demoEmail,
        ]);
        $_SESSION['demo_preview'] = true;

        redirect(brand_url($brand, 'dashboard'));
    } catch (mysqli_sql_exception $exception) {
        $connection->rollback();
        throw $exception;
    }
}

function handle_logout(array $brand): void
{
    logout_user();
    redirect(brand_url($brand));
}
