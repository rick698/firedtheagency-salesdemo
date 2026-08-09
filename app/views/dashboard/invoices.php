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
        <a href="<?= e(brand_url($brand, 'dashboard')) ?>" class="nav-link">
            <i class="fas fa-chart-line"></i> Results Dashboard
        </a>
        <a href="<?= e(brand_url($brand, 'create-project')) ?>" class="nav-link">
            <i class="fas fa-sliders"></i> Configure Your Project
        </a>
        <a href="<?= e(brand_url($brand, 'budget')) ?>" class="nav-link">
            <i class="fas fa-wallet"></i> Your Budget
        </a>
        <a href="<?= e(brand_url($brand, 'create-project') . '&new=1') ?>" class="nav-link nav-action-button">
            <i class="fas fa-plus"></i> Create New Project
        </a>
    </div>
    <div class="sidebar-footer">
        <a href="<?= e(brand_url($brand, 'profile')) ?>" class="nav-link">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="<?= e(brand_url($brand, 'invoices')) ?>" class="nav-link active">
            <i class="fas fa-file-invoice-dollar"></i> Invoices
        </a>
        <a href="<?= e(brand_url($brand, 'logout')) ?>" class="nav-link danger">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<main class="main-content">
    <section class="dashboard-card">
        <h2>Invoices</h2>
        <p class="muted-copy">Invoice integration placeholder. We will connect this to the right Xero workflow once the invoice generation approach is final.</p>
    </section>
</main>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/client.php';
