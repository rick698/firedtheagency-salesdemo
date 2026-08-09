<?php ob_start(); ?>
<section class="auth-panel">
    <div class="brand-mark">
        <span><?= e($brand['short_name']) ?></span>
    </div>
    <h1>Login</h1>
    <p>Access your campaign dashboard.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert-box">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="stacked-form" action="<?= e(brand_url($brand, 'login')) ?>" method="post">
        <label>
            <span>Email</span>
            <input type="email" name="email" required>
        </label>
        <label>
            <span>Password</span>
            <input type="password" name="password" required>
        </label>
        <button class="primary-button" type="submit">Login</button>
    </form>
</section>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/public.php';
