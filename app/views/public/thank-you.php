<?php ob_start(); ?>
<section class="auth-panel">
    <div class="brand-mark">
        <span><?= e($brand['short_name']) ?></span>
    </div>
    <h1>Thank You</h1>
    <p>Your details have been received. We will be in touch with the next step.</p>
    <a class="primary-button" href="<?= e(brand_url($brand, 'dashboard')) ?>">Continue</a>
</section>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/public.php';
