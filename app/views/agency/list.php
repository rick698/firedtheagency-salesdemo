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
    <title><?= e($title ?? 'Agency Leads') ?></title>
    <link rel="stylesheet" href="/shared/assets/css/agency.css">
</head>
<body class="agency-page">
    <header class="agency-header">
        <div>
            <span>Fired The Agency</span>
            <h1>Leads</h1>
        </div>
        <a href="/index.php?page=agency-logout">Logout</a>
    </header>

    <main class="agency-shell">
        <table class="agency-table">
            <thead>
                <tr>
                    <th>Business name</th>
                    <th>Domain / URL</th>
                    <th>Project</th>
                    <th>Stage</th>
                    <th>Email</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr onclick="window.location.href='/index.php?page=agency&lead=<?= e((string) $lead['id']) ?>'">
                        <td><strong><?= e($lead['business_name'] ?? 'Untitled business') ?></strong></td>
                        <td><?= e($lead['website'] ?? '') ?></td>
                        <td><?= e($lead['campaign_name'] ?? 'No project yet') ?></td>
                        <td><span class="stage-pill"><?= e($lead['stage']) ?></span></td>
                        <td><?= e($lead['email'] ?: ($lead['user_email'] ?? '')) ?></td>
                        <td><?= e(agency_perth_time($lead['campaign_updated_at'] ?? $lead['updated_at'] ?? $lead['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
