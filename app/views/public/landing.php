<?php ob_start(); ?>
<section class="subscribe-hero">
    <div class="brand-mark">
        <span><?= e($brand['short_name']) ?></span>
    </div>
    <h1><?= e($brand['name']) ?></h1>
    <p>Launch your local advertising campaign and review the results from one simple client dashboard.</p>
    <form action="<?= e(brand_url($brand, 'subscribe')) ?>" method="post">
        <button class="primary-button" type="submit">
            <i class="fa-solid fa-credit-card"></i>
            Subscribe
        </button>
    </form>
    <a class="subtle-link" href="<?= e(brand_url($brand, 'login')) ?>">Already subscribed? Login</a>
</section>
<script>
  if (typeof window.trackFunnelEvent === 'function') {
    window.trackFunnelEvent('landing_page_visit');
  }
</script>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/public.php';
