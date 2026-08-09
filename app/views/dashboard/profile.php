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
        <a href="<?= e(brand_url($brand, 'profile')) ?>" class="nav-link active">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="<?= e(brand_url($brand, 'invoices')) ?>" class="nav-link">
            <i class="fas fa-file-invoice-dollar"></i> Invoices
        </a>
        <a href="<?= e(brand_url($brand, 'logout')) ?>" class="nav-link danger">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<main class="main-content">
    <section class="dashboard-card profile-card">
        <h2>Profile</h2>
        <?php if (!empty($_GET['saved'])): ?>
            <div class="success-box">Profile saved.</div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert-box">
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form class="form-grid" action="<?= e(brand_url($brand, 'profile')) ?>" method="post">
            <label>
                <span>Name</span>
                <input type="text" name="name" value="<?= e($user['name']) ?>" required>
            </label>
            <label>
                <span>Email Address</span>
                <input type="email" name="email" value="<?= e($user['email']) ?>" required>
            </label>
            <label>
                <span>Official Business Name</span>
                <input type="text" name="official_business_name" value="<?= e($business['official_business_name'] ?? '') ?>">
            </label>
            <label>
                <span>ABN</span>
                <input type="text" name="abn" value="<?= e($business['abn'] ?? '') ?>">
            </label>
            <label class="wide">
                <span>Business Address</span>
                <textarea name="business_address" rows="3"><?= e($business['business_address'] ?? '') ?></textarea>
            </label>
            <label>
                <span>Phone Number (optional)</span>
                <input type="text" name="phone" value="<?= e($business['phone'] ?? '') ?>">
            </label>
            <label>
                <span>Change Password</span>
                <input type="password" name="password" minlength="8" placeholder="Leave blank to keep current password">
            </label>
            <div class="wide">
                <button class="primary-button" type="submit">Save Profile</button>
            </div>
        </form>
    </section>
</main>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/client.php';
