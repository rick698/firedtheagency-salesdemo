<?php ob_start(); ?>
<section class="auth-panel">
    <div class="brand-mark">
        <span><?= e($brand['short_name']) ?></span>
    </div>
    <h1>Create Your Account</h1>
    <p>Create your account first, then configure your Google Ads project and choose your campaign package.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert-box">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="stacked-form" action="<?= e(brand_url($brand, 'register')) ?>" method="post">
        <label>
            <span>Your Name</span>
            <input type="text" name="name" required>
        </label>
        <label>
            <span>Business Name</span>
            <input type="text" name="business_name" required>
        </label>
        <label>
            <span>Email</span>
            <input type="email" name="email" required>
        </label>
        <label>
            <span>Password</span>
            <input type="password" name="password" minlength="8" required>
        </label>
        <button class="primary-button" type="submit">Create Account</button>
    </form>
</section>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/public.php';
