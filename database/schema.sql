CREATE TABLE brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    domain VARCHAR(190) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE businesses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id INT UNSIGNED NOT NULL,
    business_name VARCHAR(190) NOT NULL,
    official_business_name VARCHAR(190) NULL,
    abn VARCHAR(40) NULL,
    contact_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(60) NULL,
    business_address TEXT NULL,
    website VARCHAR(255) NULL,
    industry VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_businesses_brand_id (brand_id),
    CONSTRAINT fk_businesses_brand_id FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id INT UNSIGNED NOT NULL,
    business_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(60) NOT NULL DEFAULT 'owner',
    status VARCHAR(60) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_users_brand_email (brand_id, email),
    INDEX idx_users_business_id (business_id),
    CONSTRAINT fk_users_brand_id FOREIGN KEY (brand_id) REFERENCES brands(id),
    CONSTRAINT fk_users_business_id FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id INT UNSIGNED NOT NULL,
    business_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status VARCHAR(80) NOT NULL,
    plan_name VARCHAR(120) NOT NULL,
    amount_cents INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'aud',
    stripe_customer_id VARCHAR(190) NULL,
    stripe_checkout_session_id VARCHAR(190) NULL,
    stripe_subscription_id VARCHAR(190) NULL,
    stripe_payment_intent_id VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_subscriptions_brand_id (brand_id),
    INDEX idx_subscriptions_business_id (business_id),
    INDEX idx_subscriptions_user_id (user_id),
    INDEX idx_subscriptions_stripe_customer_id (stripe_customer_id),
    CONSTRAINT fk_subscriptions_brand_id FOREIGN KEY (brand_id) REFERENCES brands(id),
    CONSTRAINT fk_subscriptions_business_id FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_subscriptions_user_id FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    campaign_name VARCHAR(190) NOT NULL,
    campaign_type VARCHAR(80) NOT NULL,
    budget_cents INT UNSIGNED NULL,
    target_location VARCHAR(190) NULL,
    target_audience TEXT NULL,
    goals TEXT NULL,
    status VARCHAR(80) NOT NULL DEFAULT 'draft',
    start_date DATE NULL,
    end_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_campaigns_business_id (business_id),
    CONSTRAINT fk_campaigns_business_id FOREIGN KEY (business_id) REFERENCES businesses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campaign_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NULL,
    result_date DATE NOT NULL,
    impressions INT UNSIGNED NOT NULL DEFAULT 0,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    leads INT UNSIGNED NOT NULL DEFAULT 0,
    conversions INT UNSIGNED NOT NULL DEFAULT 0,
    spend_cents INT UNSIGNED NOT NULL DEFAULT 0,
    revenue_cents INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_campaign_results_date (campaign_id, result_date),
    CONSTRAINT fk_campaign_results_campaign_id FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE budget_change_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id INT UNSIGNED NOT NULL,
    business_id INT UNSIGNED NOT NULL,
    campaign_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    old_budget_cents INT UNSIGNED NULL,
    requested_budget_cents INT UNSIGNED NOT NULL,
    status VARCHAR(80) NOT NULL DEFAULT 'requested',
    stripe_subscription_id VARCHAR(190) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_budget_change_business_id (business_id),
    INDEX idx_budget_change_campaign_id (campaign_id),
    INDEX idx_budget_change_status (status),
    CONSTRAINT fk_budget_change_brand_id FOREIGN KEY (brand_id) REFERENCES brands(id),
    CONSTRAINT fk_budget_change_business_id FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_budget_change_campaign_id FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    CONSTRAINT fk_budget_change_user_id FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stripe_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(190) NOT NULL,
    event_type VARCHAR(190) NOT NULL,
    handling_status VARCHAR(190) NOT NULL,
    payload MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stripe_events_event_id (event_id),
    INDEX idx_stripe_events_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO brands (id, slug, name, domain)
VALUES
    (1, 'smallbusinessdigitalservices', 'Small Business Digital Services', 'clients.smallbusinessdigitalservices.com.au'),
    (2, 'firedtheagency', 'Fired The Agency', 'clients.firedtheagency.com')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    domain = VALUES(domain);
