ALTER TABLE businesses
    ADD COLUMN official_business_name VARCHAR(190) NULL AFTER business_name,
    ADD COLUMN abn VARCHAR(40) NULL AFTER official_business_name,
    ADD COLUMN business_address TEXT NULL AFTER phone;

CREATE TABLE IF NOT EXISTS budget_change_requests (
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
