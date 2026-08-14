<?php
$budget = isset($campaign['budget_cents']) && $campaign['budget_cents'] !== null ? (int) round(((int) $campaign['budget_cents']) / 100) : 400;
$checkoutError = $_SESSION['checkout_error'] ?? '';
$paymentCancelled = ($_GET['checkout'] ?? '') === 'cancelled' || ($_GET['checkout_error'] ?? '') === 'terms';
$target = $campaign['target_audience_data'] ?? [];
$keywords = array_values(array_filter($keywords ?? []));
$service = trim((string) ($target['service_short'] ?? 'service'));
$city = trim((string) ($target['service_area'] ?? 'your area'));
$lat = (float) ($target['target_lat'] ?? -31.9523);
$lng = (float) ($target['target_lng'] ?? 115.8613);
$radiusKm = max(1, min(17, (int) ($target['target_radius_km'] ?? 17)));
$websiteHost = parse_url((string) ($business['website'] ?? ''), PHP_URL_HOST) ?: (string) ($business['website'] ?? 'yourwebsite.com.au');
$websiteHost = preg_replace('#^www\.#i', '', trim($websiteHost)) ?: 'yourwebsite.com.au';
$goalsData = $campaign['goals_data'] ?? [];
$adPreview = is_array($goalsData['ad_preview'] ?? null) ? $goalsData['ad_preview'] : [];

if (empty($adPreview['headline']) || empty($adPreview['description_line_1']) || empty($adPreview['description_line_2'])) {
    $adPreview = fallback_demo_ad_preview($service, $city, (string) ($goalsData['why_choose'] ?? ''));
}

$adHeadline = (string) ($adPreview['headline'] ?? '');
$adDescriptionLineOne = (string) ($adPreview['description_line_1'] ?? '');
$adDescriptionLineTwo = (string) ($adPreview['description_line_2'] ?? '');
unset($_SESSION['checkout_error']);
ob_start();
?>
<div class="mobile-header">
    <a class="navbar-brand brand-font" href="<?= e(brand_url($brand, 'dashboard')) ?>"><?= e($brand['logo_text']) ?></a>
    <button class="icon-button" type="button" onclick="toggleSidebar()" aria-label="Open menu">
        <i class="fas fa-bars"></i>
    </button>
</div>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a class="brand-font" href="<?= e(brand_url($brand, 'dashboard')) ?>">
            <i class="fas fa-chart-simple"></i>
            <?= e($brand['logo_text']) ?>
        </a>
    </div>

    <div class="nav-list">
        <a href="<?= e(brand_url($brand, 'dashboard')) ?>" class="nav-link">
            <i class="fas fa-chart-line"></i> Results Dashboard
        </a>
        <a href="<?= e(brand_url($brand, 'create-project')) ?>" class="nav-link nav-action-button <?= !empty($draftProject) ? 'draft-action-button' : '' ?>">
            <i class="fas <?= !empty($draftProject) ? 'fa-pen-to-square' : 'fa-plus' ?>"></i> <?= !empty($draftProject) ? 'Proceed With Draft Project' : 'Create New Project' ?>
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="<?= e(brand_url($brand, 'logout')) ?>" class="nav-link danger">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<main class="main-content">
    <section class="final-hero">
        <span class="final-kicker"><i class="fas fa-circle-check"></i> Project saved</span>
        <h1>That's it! View your setup.</h1>
        <p>We will start with an ad budget of <strong>$<?= e(number_format($budget)) ?> AUD</strong>, plus GST.</p>
    </section>

    <section
        class="setup-preview"
        data-keywords='<?= e(json_encode($keywords, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
        data-lat="<?= e((string) $lat) ?>"
        data-lng="<?= e((string) $lng) ?>"
        data-radius="<?= e((string) $radiusKm) ?>"
    >
        <div class="setup-preview-heading">
            <h2>Your ad could be visible to many people searching</h2>
            <p>This is a demo ad, live versions will be reviewed both by us and yourself before it goes live.</p>
        </div>

        <div class="google-demo">
            <div class="google-top">
                <div class="google-logo">Google</div>
                <div class="google-search-shell">
                    <span class="google-keyword"><?= e($keywords[0] ?? ($service . ' ' . $city)) ?></span>
                    <i class="fas fa-magnifying-glass"></i>
                </div>
            </div>
            <div class="google-tabs">
                <span class="active">All</span>
                <span>Maps</span>
                <span>Images</span>
                <span>Videos</span>
                <span>News</span>
            </div>
            <div class="organic-blur"></div>
            <article class="demo-ad-card">
                <div class="demo-ad-business">
                    <span>Ad</span>
                    <div>
                        <strong><?= e($business['business_name'] ?? 'Your Business') ?></strong>
                        <small><?= e($websiteHost) ?></small>
                    </div>
                </div>
                <div class="sponsored">Sponsored</div>
                <h3><?= e($adHeadline) ?></h3>
                <p>
                    <span><?= e($adDescriptionLineOne) ?></span>
                    <span><?= e($adDescriptionLineTwo) ?></span>
                </p>
                <div class="demo-ad-actions">
                    <span>Visit website</span>
                    <span>Call now</span>
                    <span>Get quote</span>
                </div>
            </article>
            <div class="organic-blur lower"></div>
        </div>

        <div class="preview-map-wrap">
            <div id="previewMap"></div>
        </div>
    </section>

    <section class="pricing-section">
        <div class="pricing-heading">
            <h2>Start attracting visitors to your site now!</h2>
            <?php if ($paymentCancelled): ?>
                <div class="payment-cancelled-bar">
                    <strong>The payment was cancelled. Try again with a different payment method and we didn't start yet.</strong>
                    <span>No payment has been made yet.</span>
                </div>
            <?php endif; ?>
            <?php if ($checkoutError !== ''): ?>
                <div class="alert-box"><?= e($checkoutError) ?></div>
            <?php endif; ?>
        </div>

        <div class="pricing-grid">
            <article class="pricing-card agency-card">
                <span class="plan-label danger-text"><i class="fas fa-times-circle"></i> Typical Agency</span>
                <div class="plan-price muted-strike">$2,500</div>
                <p class="plan-subtitle">Per month, before ad spend</p>
                <ul>
                    <li><i class="fas fa-times"></i> Slow manual setup</li>
                    <li><i class="fas fa-times"></i> Long-term contracts</li>
                    <li><i class="fas fa-times"></i> 20% of ad spend</li>
                    <li><i class="fas fa-times"></i> Paying for overhead</li>
                </ul>
                <a href="<?= e(brand_url($brand, 'create-project')) ?>" class="pricing-button muted">Get started</a>
            </article>

            <article class="pricing-card standard-card">
                <span class="popular-badge blue">Most Popular</span>
                <span class="plan-label blue-text">Smart Choice</span>
                <div class="plan-price">$67</div>
                <p class="plan-subtitle">AUD per month + 10% of ad spend, plus GST</p>
                <ul>
                    <li><i class="fas fa-check"></i> Automated ad setup</li>
                    <li><i class="fas fa-check"></i> Live results dashboard</li>
                    <li><i class="fas fa-check"></i> Cancel any month</li>
                    <li><i class="fas fa-check"></i> Setup fee reduced from $197 to $97 one off, plus GST</li>
                </ul>
                <form class="checkout-form" action="<?= e(brand_url($brand, 'stripe-checkout')) ?>" method="post">
                    <input type="hidden" name="plan" value="standard">
                    <label class="terms-check">
                        <input type="checkbox" name="accept_terms" value="1">
                        <span>By starting you agree with the <a href="https://www.firedtheagency.com/TAC" target="_blank">terms and conditions</a>.</span>
                    </label>
                    <div class="terms-warning"><span>&uarr;</span> You have to agree with the terms and conditions before you can proceed.</div>
                    <button type="submit" class="pricing-button blue">Get started</button>
                </form>
            </article>

            <article class="pricing-card pro-card">
                <span class="popular-badge yellow">Premium Solution</span>
                <span class="plan-label yellow-text">The Pro System</span>
                <div class="plan-price">$139</div>
                <p class="plan-subtitle">AUD per month + 10% of ad spend, plus GST</p>
                <ul>
                    <li><i class="fas fa-check"></i> Everything in Standard</li>
                    <li><i class="fas fa-check"></i> CRM, automated, lead followup</li>
                    <li><i class="fas fa-bolt"></i> Priority optimisation review</li>
                    <li><i class="fas fa-forward"></i> Fast-track onboarding</li>
                    <li><i class="fas fa-file-alt"></i> Setup fee reduced from $197 to $97 one off, plus GST</li>
                </ul>
                <form class="checkout-form" action="<?= e(brand_url($brand, 'stripe-checkout')) ?>" method="post">
                    <input type="hidden" name="plan" value="pro">
                    <label class="terms-check">
                        <input type="checkbox" name="accept_terms" value="1">
                        <span>By starting you agree with the <a href="https://www.firedtheagency.com/TAC" target="_blank">terms and conditions</a>.</span>
                    </label>
                    <div class="terms-warning"><span>&uarr;</span> You have to agree with the terms and conditions before you can proceed.</div>
                    <button type="submit" class="pricing-button yellow">Get started</button>
                </form>
            </article>
        </div>
    </section>
</main>
<script>
  if (typeof window.trackFunnelEvent === 'function') {
    window.trackFunnelEvent('demo_overview_open');
  }
  if (typeof window.trackGa4PageView === 'function') {
    window.trackGa4PageView('/demo/overview', 'Demo Overview');
  }
</script>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/client.php';
