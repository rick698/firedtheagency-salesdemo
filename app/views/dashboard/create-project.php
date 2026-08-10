<?php
$goals = $draftProject['goals_data'] ?? [];
$targetAudience = $draftProject['target_audience_data'] ?? [];
$projectName = $draftProject['campaign_name'] ?? 'Google Ads';
$businessWebsite = $business['website'] ?? '';
$accountEmail = $business['email'] ?? ($user['email'] ?? '');
$story = $goals['story'] ?? '';
$whyChoose = $goals['why_choose'] ?? '';
$serviceArea = $draftProject['target_location'] ?? ($targetAudience['service_area'] ?? '');
$serviceDescription = $targetAudience['service_description'] ?? '';
$serviceShort = $targetAudience['service_short'] ?? '';
$sweetener = $targetAudience['sweetener'] ?? '';
$exclusions = $targetAudience['exclusions'] ?? '';
$targetRadius = (int) ($targetAudience['target_radius_km'] ?? 17);
$targetRadius = max(1, min(17, $targetRadius));
$targetLat = $targetAudience['target_lat'] ?? '';
$targetLng = $targetAudience['target_lng'] ?? '';
$monthlyBudget = isset($draftProject['budget_cents']) && $draftProject['budget_cents'] !== null ? (int) round(((int) $draftProject['budget_cents']) / 100) : 400;
$isDemoPreview = !empty($_SESSION['demo_preview']);

if ($isDemoPreview && str_starts_with((string) $accountEmail, 'demo+')) {
    $accountEmail = '';
}

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
        <a href="<?= e(brand_url($brand, 'create-project')) ?>" class="nav-link nav-action-button <?= !empty($draftProject) ? 'draft-action-button' : '' ?> active">
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
    <form class="project-wizard" action="<?= e(brand_url($brand, 'create-project')) ?>" method="post" data-step-save-url="<?= e(brand_url($brand, 'create-project-step')) ?>">
        <?php if (!empty($errors)): ?>
            <div class="alert-box">
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="wizard-step active" data-step="1">
            <div class="step-kicker">Step 1 of 3. Finishing this step usually takes around 2 minutes.</div>
            <div class="section-header">
                <h1>Your Business Details</h1>
                <p>What are we telling prospects about your business?</p>
            </div>
            <input type="hidden" name="campaign_name" value="<?= e($projectName) ?>">

            <div class="form-grid">
                <label>
                    <span>Business Name</span>
                    <input type="text" name="business_name" value="<?= e($business['business_name']) ?>" required>
                </label>
                <label>
                    <span>URL / Domain</span>
                    <input type="text" name="business_website" value="<?= e($businessWebsite) ?>" placeholder="example.com.au" required>
                </label>
                <label class="wide">
                    <span>Email Address</span>
                    <input type="email" name="account_email" value="<?= e($accountEmail) ?>" required>
                </label>
                <label class="wide">
                    <span>Your service at the highest level in max 3 words (only 1 service allowed - like lawn care, plumbing, electrician, Concrete polishing)</span>
                    <input type="text" name="service_short" value="<?= e($serviceShort) ?>" maxlength="80" required>
                </label>
                <label class="wide">
                    <span>Why Choose You?</span>
                    <small class="field-help">List guarantees, speed, service quality, certifications, pricing advantages, or anything specific.</small>
                    <textarea name="why_choose" rows="4"><?= e($whyChoose) ?></textarea>
                </label>
                <div class="service-area-heading wide">Service Area</div>
                <input type="hidden" name="service_area" value="<?= e($serviceArea) ?>">
                <input type="hidden" name="target_lat" value="<?= e((string) $targetLat) ?>">
                <input type="hidden" name="target_lng" value="<?= e((string) $targetLng) ?>">
                <input type="hidden" name="target_radius_km" value="<?= e((string) $targetRadius) ?>">
            </div>

            <div class="service-radius-block">
                <label class="radius-title"><i class="fas fa-map-marker-alt"></i> Service Radius</label>
                <div class="radius-layout">
                    <div>
                        <p class="radius-note"><i class="fas fa-crosshairs"></i> Click anywhere on the map to re-center your target area.</p>
                        <div id="map"></div>
                    </div>
                    <div class="radius-controls">
                        <label>Radius: <span id="radiusValue"><?= e((string) $targetRadius) ?></span> km</label>
                        <input type="range" min="1" max="17" step="1" id="radiusSlider" value="<?= e((string) $targetRadius) ?>" oninput="updateRadius(this.value)">

                        <hr>

                        <label class="toggle-row">
                            <input type="checkbox" id="useCurrentLoc" checked>
                            <span>Center map on my location</span>
                        </label>

                        <div class="manual-city">
                            <label>Manual City Entry</label>
                            <div class="city-search-row">
                                <input type="text" id="manualCity" value="<?= e($serviceArea !== '' ? $serviceArea : 'Perth, WA 6000') ?>">
                                <button class="secondary-button" type="button" onclick="findCity()">Find city</button>
                            </div>
                        </div>

                        <p class="radius-help">
                            <i class="fas fa-info-circle"></i> We cap this at 17km. Any larger and you pay for clicks that are too far to drive.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="wizard-step" data-step="2">
            <div class="step-kicker">Step 2 of 3. Finishing this step usually takes around 2 minutes.</div>
            <div class="section-header">
                <h1>Describe Your Service</h1>
                <p>Describe one service only. You can add edging to lawn mowing, because that is one industry/service. Lawn mowing and gutter cleaning are two different services.</p>
            </div>

            <div class="form-grid single">
                <label>
                    <span>Your service in detail:</span>
                    <textarea name="service_description" rows="5" placeholder="Example: Residential cleaning, end-of-lease cleans, lawn mowing, emergency plumbing..." required><?= e($serviceDescription) ?></textarea>
                </label>
                <label>
                    <span>The Sweetener</span>
                    <textarea name="sweetener" rows="4" placeholder="Example: same-day quotes, weekend appointments, guarantee, free inspection..."><?= e($sweetener) ?></textarea>
                </label>
                <label>
                    <span>Exclusions</span>
                    <textarea name="exclusions" rows="4" placeholder="What do you not do? This helps avoid wasted ad spend."><?= e($exclusions) ?></textarea>
                </label>
            </div>
        </section>

        <section class="wizard-step" data-step="3">
            <div class="step-kicker">Step 3 of 3. Finishing this step usually takes around 2 minutes.</div>
            <div class="section-header">
                <h1>Fuel The Engine</h1>
                <p>Set the monthly ad spend cap for this project. This budget is plus GST when charged.</p>
            </div>

            <div class="budget-layout">
                <label>
                    <span>Monthly Cap</span>
                    <input type="number" name="monthly_budget" value="<?= e((string) $monthlyBudget) ?>" min="50" step="1" required>
                </label>
                <div class="budget-alert">
                    <h3>Safe Spend Protocol</h3>
                    <p>We aim to spend the budget carefully. Your chosen amount is the ad budget before GST, so a $200 budget is charged as $220 including GST.</p>
                </div>
            </div>
            <div class="strategy-box">
                <h3>The Safe Digital Service Strategy</h3>
                <p>You can cancel any month, there is no lock-in contract, and you can change your budget at any given time.</p>
            </div>
        </section>

        <div class="wizard-actions">
            <button class="secondary-button" type="button" id="wizardBack">Back</button>
            <button class="primary-button" type="button" id="wizardNext">Next</button>
            <button class="primary-button hidden" type="submit" id="wizardSave">Save Project</button>
        </div>

        <?php if ($isDemoPreview): ?>
            <div class="modal-backdrop password-modal-backdrop" id="passwordModal" aria-hidden="true">
                <div class="tax-modal">
                    <h2>Password</h2>
                    <p>Create a password so you can log in later and get back to your details.</p>
                    <label>
                        <span>Password</span>
                        <input type="password" name="account_password" minlength="8" autocomplete="new-password">
                    </label>
                    <div class="wizard-actions">
                        <button class="secondary-button" type="button" id="passwordCancel">Back</button>
                        <button class="primary-button" type="button" id="passwordContinue">Save Project</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </form>
</main>
<script>
  oaiq("track", "checkout_started", {
    source: "create_project_page",
    brand: "small_business_digital_services"
  });
</script>
<script>
  gtag("event", "begin_checkout", {
    currency: "AUD"
  });
</script>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/client.php';
