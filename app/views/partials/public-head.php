<!DOCTYPE html>
<html lang="en">
<head>
    <?php
        $trackingConfig = tracking_config();
        $ga4MeasurementId = trim((string) ($trackingConfig['ga4_measurement_id'] ?? ''));
        $metaPixelId = trim((string) ($trackingConfig['meta_pixel_id'] ?? ''));
        $trackingEndpoint = brand_url($brand, 'track-event');
    ?>
    <?php if ($ga4MeasurementId !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4MeasurementId) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= e($ga4MeasurementId) ?>');
    </script>
    <?php endif; ?>
    <?php if ($metaPixelId !== ''): ?>
    <script>
      !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
      n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script',
      'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', '<?= e($metaPixelId) ?>');
      fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= e($metaPixelId) ?>&ev=PageView&noscript=1"></noscript>
    <?php endif; ?>
    <script>
      window.ftaTracking = {
        endpoint: <?= json_encode($trackingEndpoint) ?>,
        brand: <?= json_encode($brand['slug'] ?? 'firedtheagency') ?>,
        query: <?= json_encode(tracking_query_params()) ?>
      };
      (function () {
        function eventId() {
          if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
          }

          return 'evt_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2);
        }

        window.trackFunnelEvent = function (name, params) {
          var tracking = window.ftaTracking || {};
          var eventParams = Object.assign({}, tracking.query || {}, params || {});
          var id = eventParams.event_id || eventId();
          eventParams.event_id = id;
          eventParams.brand = tracking.brand || 'firedtheagency';
          eventParams.page_url = window.location.href;
          eventParams.referrer = document.referrer || '';
          var metaName = eventParams.meta_event_name || name;
          var gaParams = Object.assign({}, eventParams);
          var metaParams = Object.assign({}, eventParams);
          delete gaParams.meta_event_name;
          delete metaParams.meta_event_name;

          if (typeof window.gtag === 'function') {
            window.gtag('event', name, gaParams);
          }

          if (typeof window.fbq === 'function') {
            var metaStandardEvents = {
              PageView: true,
              ViewContent: true,
              Lead: true,
              InitiateCheckout: true
            };
            window.fbq(metaStandardEvents[metaName] ? 'track' : 'trackCustom', metaName, metaParams, { eventID: id });
          }

          if (tracking.endpoint && window.fetch) {
            window.fetch(tracking.endpoint, {
              method: 'POST',
              credentials: 'same-origin',
              keepalive: true,
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(Object.assign({ event_name: metaName, ga_event_name: name }, metaParams))
            }).catch(function () {});
          }

          return id;
        };

        window.trackGa4PageView = function (path, title, params) {
          if (typeof window.gtag !== 'function') {
            return;
          }

          var tracking = window.ftaTracking || {};
          var pageParams = Object.assign({}, tracking.query || {}, params || {});
          pageParams.brand = tracking.brand || 'firedtheagency';
          pageParams.page_path = path;
          pageParams.page_title = title;
          pageParams.page_location = window.location.origin + path;
          window.gtag('event', 'page_view', pageParams);
        };
      })();
    </script>
    <script>!function(w,d,s,u){if(w.oaiq)return;var q=function(){q.q.push(arguments)};q.q=[];w.oaiq=q;var j=d.createElement(s);j.async=1;j.src=u;var f=d.getElementsByTagName(s)[0];f.parentNode.insertBefore(j,f)}(window,document,"script","https://bzrcdn.openai.com/sdk/oaiq.min.js");oaiq("init",{pixelId:"3CpQMg6u3hMqYJhHvAtWQd",debug:true});</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? $brand['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="/shared/assets/css/dashboard.css?v=<?= e((string) filemtime(APP_ROOT . '/shared/assets/css/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e($brand['public_path']) ?>/assets/css/brand.css?v=<?= e((string) filemtime(APP_ROOT . $brand['public_path'] . '/assets/css/brand.css')) ?>">
</head>
