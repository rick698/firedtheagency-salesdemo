<?php ob_start(); ?>
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

    <?php if (!empty($activeSubscription)): ?>
        <div class="project-select-wrapper">
            <label>Active Projects</label>
            <select class="form-select-dark">
                <option>Google Ads - <?= e(project_service_label($latestProject ?? null, $business['business_name'] ?? 'Your Business')) ?></option>
            </select>
        </div>
    <?php endif; ?>

    <div class="nav-list">
        <a href="<?= e(brand_url($brand, 'dashboard')) ?>" class="nav-link active">
            <i class="fas fa-chart-line"></i> Results Dashboard
        </a>
        <?php if (!empty($activeSubscription)): ?>
            <a href="<?= e(brand_url($brand, 'create-project')) ?>" class="nav-link">
                <i class="fas fa-sliders"></i> Configure Your Project
            </a>
            <a href="<?= e(brand_url($brand, 'budget')) ?>" class="nav-link">
                <i class="fas fa-wallet"></i> Your Budget
            </a>
        <?php endif; ?>
        <a href="<?= e(!empty($activeSubscription) ? brand_url($brand, 'create-project') . '&new=1' : brand_url($brand, 'create-project')) ?>" class="nav-link nav-action-button <?= !empty($draftProject) ? 'draft-action-button' : '' ?>">
            <i class="fas <?= !empty($draftProject) ? 'fa-pen-to-square' : 'fa-plus' ?>"></i> <?= !empty($draftProject) ? 'Proceed With Draft Project' : 'Create New Project' ?>
        </a>
    </div>

    <div class="sidebar-footer">
        <?php if (!empty($activeSubscription)): ?>
            <a href="<?= e(brand_url($brand, 'profile')) ?>" class="nav-link">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="<?= e(brand_url($brand, 'invoices')) ?>" class="nav-link">
                <i class="fas fa-file-invoice-dollar"></i> Invoices
            </a>
        <?php endif; ?>
        <a href="<?= e(brand_url($brand, 'logout')) ?>" class="nav-link danger">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<main class="main-content">
    <?php if (!empty($activeSubscription)): ?>
        <section class="welcome-panel paid-empty-panel">
            <div>
                <h2>Your project is being created.</h2>
                <p>You will receive an update soon. We'll inform you by e-mail.</p>
            </div>
        </section>
    <?php else: ?>
        <section class="welcome-panel">
        <div>
            <h2>Hi there <?= e($user['name']) ?>, this is a demo dashboard for <?= e($business['business_name']) ?>.</h2>
            <p>This is dummy data, but it will be replaced with your results as soon as we get started!</p>
        </div>
        <a class="start-project-button <?= !empty($draftProject) ? 'draft-start-button' : '' ?>" href="<?= e(brand_url($brand, 'create-project')) ?>">
            <i class="fas <?= !empty($draftProject) ? 'fa-pen-to-square' : 'fa-plus' ?>"></i>
            <?= !empty($draftProject) ? 'Proceed with draft project' : 'Create your project' ?>
        </a>
        </section>

        <div class="dashboard-title-row compact">
        <div>
            <h2>Demo Campaign Performance</h2>
            <p>Sample reporting layout.</p>
        </div>
        </div>

        <section class="scorecard-grid">
        <article class="scorecard">
            <div class="scorecard-label">Impressions</div>
            <div class="scorecard-value">12,450</div>
            <div class="trend-up"><i class="fas fa-arrow-up"></i> 12% vs last month</div>
        </article>
        <article class="scorecard">
            <div class="scorecard-label">Clicks</div>
            <div class="scorecard-value">482</div>
            <div class="trend-up"><i class="fas fa-arrow-up"></i> 5% vs last month</div>
        </article>
        <article class="scorecard">
            <div class="scorecard-label">CTR</div>
            <div class="scorecard-value accent">3.8%</div>
            <div class="trend-up"><i class="fas fa-arrow-up"></i> 0.2% vs last month</div>
        </article>
        <article class="scorecard">
            <div class="scorecard-label">Total Cost</div>
            <div class="scorecard-value">$315.20</div>
            <div class="muted-small">Avg CPC: $0.65</div>
        </article>
        </section>

        <section class="dashboard-card">
        <div class="card-heading">
            <h3>Traffic vs. Cost</h3>
            <span>Last 7 Days</span>
        </div>
        <div class="chart-wrap">
            <canvas id="performanceChart"></canvas>
        </div>
        </section>

        <section class="dashboard-card">
        <h3>Monthly Performance Breakdown</h3>
        <div class="table-responsive">
            <table class="stats-table" id="statsTable">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-end">Impressions</th>
                        <th class="text-end">Clicks</th>
                        <th class="text-end">CTR</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Avg CPC</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
        </section>
    <?php endif; ?>
</main>
<?php if (!empty($showTaxModal)): ?>
    <div class="modal-backdrop active">
        <form class="tax-modal" action="<?= e(brand_url($brand, 'tax-details')) ?>" method="post">
            <h2>Tax details</h2>
            <p>These details help us prepare your account correctly.</p>
            <label>
                <span>Official Business Name</span>
                <input type="text" name="official_business_name" value="<?= e($business['official_business_name'] ?? $business['business_name']) ?>" required>
            </label>
            <label>
                <span>ABN</span>
                <input type="text" name="abn" value="<?= e($business['abn'] ?? '') ?>" required>
            </label>
            <label>
                <span>Business Address</span>
                <textarea name="business_address" rows="3" required><?= e($business['business_address'] ?? '') ?></textarea>
            </label>
            <label>
                <span>Phone Number (optional)</span>
                <input type="text" name="phone" value="<?= e($business['phone'] ?? '') ?>">
            </label>
            <button class="primary-button" type="submit">Submit</button>
            <p class="privacy-note">Your details will be used privacy-friendly according to our terms and conditions.</p>
        </form>
    </div>
<?php endif; ?>
<?php if (!empty($showBudgetModal) && !empty($latestProject)): ?>
    <?php $startingBudget = isset($latestProject['budget_cents']) && $latestProject['budget_cents'] !== null ? (int) round(((int) $latestProject['budget_cents']) / 100) : 400; ?>
    <div class="modal-backdrop active">
        <form class="tax-modal" action="<?= e(brand_url($brand, 'confirm-ad-spend-budget')) ?>" method="post">
            <h2>Last question!</h2>
            <p>We just want to confirm your starting budget. You can change this any day. Budget amounts are plus GST when charged.</p>
            <?php if (!empty($budgetError)): ?>
                <div class="alert-box"><?= e($budgetError) ?></div>
            <?php endif; ?>
            <label>
                <span>Monthly ad spend package</span>
                <input type="number" name="monthly_budget" min="50" step="1" value="<?= e((string) $startingBudget) ?>" required>
            </label>
            <p class="privacy-note">Can you confirm or adjust your monthly budget for the ad spend package you would like to start with? For example, $200 budget becomes $220 including GST.</p>
            <button class="primary-button" type="submit">Confirm budget</button>
        </form>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/client.php';
