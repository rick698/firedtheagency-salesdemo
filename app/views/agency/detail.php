<!DOCTYPE html>
<html lang="en-AU">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RPEPJVSYY2"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-RPEPJVSYY2');
    </script>
    <script>!function(w,d,s,u){if(w.oaiq)return;var q=function(){q.q.push(arguments)};q.q=[];w.oaiq=q;var j=d.createElement(s);j.async=1;j.src=u;var f=d.getElementsByTagName(s)[0];f.parentNode.insertBefore(j,f)}(window,document,"script","https://bzrcdn.openai.com/sdk/oaiq.min.js");oaiq("init",{pixelId:"3CpQMg6u3hMqYJhHvAtWQd",debug:true});</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($business['business_name'] ?? 'Lead Detail') ?> | Agency</title>
    <link rel="stylesheet" href="/shared/assets/css/agency.css">
</head>
<body class="agency-page">
    <header class="agency-header">
        <div>
            <a href="/index.php?page=agency" class="back-link">Back to leads</a>
            <h1><?= e($business['business_name'] ?? 'Untitled business') ?></h1>
        </div>
        <span class="stage-pill large"><?= e($stage) ?></span>
    </header>

    <main class="agency-shell detail-grid">
        <section class="agency-card">
            <h2>Business</h2>
            <dl>
                <dt>Name</dt><dd><?= e($business['business_name'] ?? '') ?></dd>
                <dt>Website</dt><dd><?= e($business['website'] ?? '') ?></dd>
                <dt>Email</dt><dd><?= e($business['email'] ?? '') ?></dd>
                <dt>Contact</dt><dd><?= e($business['contact_name'] ?? '') ?></dd>
                <dt>Phone</dt><dd><?= e($business['phone'] ?? '') ?></dd>
                <dt>Address</dt><dd><?= nl2br(e($business['business_address'] ?? '')) ?></dd>
            </dl>
        </section>

        <section class="agency-card">
            <h2>Login account</h2>
            <?php foreach ($users as $user): ?>
                <dl>
                    <dt>Name</dt><dd><?= e($user['name'] ?? '') ?></dd>
                    <dt>Email</dt><dd><?= e($user['email'] ?? '') ?></dd>
                    <dt>Status</dt><dd><?= e($user['status'] ?? '') ?></dd>
                    <dt>Created</dt><dd><?= e(agency_perth_time($user['created_at'] ?? '')) ?></dd>
                </dl>
            <?php endforeach; ?>
        </section>

        <section class="agency-card wide">
            <h2>Projects for this account</h2>
            <?php foreach ($projects as $project): ?>
                <?php $target = $project['target_audience_data'] ?? []; $goals = $project['goals_data'] ?? []; ?>
                <article class="project-panel">
                    <div class="project-heading">
                        <h3><?= e($project['campaign_name'] ?? 'Google Ads') ?></h3>
                        <span class="stage-pill"><?= e($project['status'] ?? '') ?></span>
                    </div>
                    <dl class="project-details">
                        <dt>Budget</dt><dd>$<?= e(number_format(((int) ($project['budget_cents'] ?? 0)) / 100, 2)) ?> ex GST</dd>
                        <dt>Service area</dt><dd><?= e($target['service_area'] ?? $project['target_location'] ?? '') ?></dd>
                        <dt>Radius</dt><dd><?= e((string) ($target['target_radius_km'] ?? '')) ?> km</dd>
                        <dt>Service</dt><dd><?= e($target['service_short'] ?? '') ?></dd>
                        <dt>Service detail</dt><dd><?= nl2br(e($target['service_description'] ?? '')) ?></dd>
                        <dt>Story</dt><dd><?= nl2br(e($goals['story'] ?? '')) ?></dd>
                        <dt>Why choose them</dt><dd><?= nl2br(e($goals['why_choose'] ?? '')) ?></dd>
                        <dt>Sweetener</dt><dd><?= nl2br(e($target['sweetener'] ?? '')) ?></dd>
                        <dt>Exclusions</dt><dd><?= nl2br(e($target['exclusions'] ?? '')) ?></dd>
                        <dt>Updated</dt><dd><?= e(agency_perth_time($project['updated_at'] ?? $project['created_at'] ?? '')) ?></dd>
                    </dl>
                </article>
            <?php endforeach; ?>
            <?php if (empty($projects)): ?>
                <p>No projects yet.</p>
            <?php endif; ?>
        </section>

        <section class="agency-card wide">
            <h2>Payments</h2>
            <?php foreach ($subscriptions as $subscription): ?>
                <?php
                    $storedAmountCents = (int) ($subscription['amount_cents'] ?? 0);
                    $displayAmountCents = match ($storedAmountCents) {
                        8690 => 7900,
                        15290 => 13900,
                        default => $storedAmountCents,
                    };
                ?>
                <dl>
                    <dt>Plan</dt><dd><?= e($subscription['plan_name'] ?? '') ?></dd>
                    <dt>Status</dt><dd><?= e($subscription['status'] ?? '') ?></dd>
                    <dt>Amount</dt><dd>$<?= e(number_format($displayAmountCents / 100, 2)) ?> ex GST</dd>
                    <dt>Stripe session</dt><dd><?= e($subscription['stripe_checkout_session_id'] ?? '') ?></dd>
                    <dt>Created</dt><dd><?= e(agency_perth_time($subscription['created_at'] ?? '')) ?></dd>
                </dl>
            <?php endforeach; ?>
            <?php if (empty($subscriptions)): ?>
                <p>No payments yet.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
