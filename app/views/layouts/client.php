<?php require APP_ROOT . '/app/views/partials/public-head.php'; ?>
<body class="dashboard-page" style="--brand-accent: <?= e($brand['accent_color']) ?>; --brand-bg: <?= e($brand['dashboard_background']) ?>; --brand-surface: <?= e($brand['dashboard_surface']) ?>; --brand-text: <?= e($brand['dashboard_text']) ?>; --brand-muted: <?= e($brand['dashboard_muted']) ?>;">
    <?= $content ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="/shared/assets/js/dashboard.js?v=<?= e((string) filemtime(APP_ROOT . '/shared/assets/js/dashboard.js')) ?>"></script>
</body>
</html>
