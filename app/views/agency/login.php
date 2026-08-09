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
    <title><?= e($title ?? 'Agency Login') ?></title>
    <link rel="stylesheet" href="/shared/assets/css/agency.css">
</head>
<body class="agency-login-page">
    <main class="agency-login-panel">
        <h1>Agency Login</h1>
        <p>Secure access for lead review.</p>
        <?php if (!empty($error)): ?>
            <div class="agency-alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/agency" class="agency-form">
            <label>
                <span>User or email</span>
                <input type="text" name="login" autocomplete="username" required>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit">Login</button>
        </form>
    </main>
</body>
</html>
