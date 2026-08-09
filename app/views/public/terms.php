<?php ob_start(); ?>
<section class="auth-panel">
    <div class="brand-mark">
        <span><?= e($brand['short_name']) ?></span>
    </div>
    <h1>Terms and Conditions</h1>
    <p>This page is a placeholder. The full terms and conditions will be added here later.</p>
    <a class="primary-button" href="<?= e(brand_url($brand, 'project-complete')) ?>">Back</a>
</section>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/public.php';
