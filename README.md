# Small Business Digital Services Client Portal

PHP + mysqli client portal for branded advertising campaign dashboards.

## Current Scope

- Test portal for `clients.smallbusinessdigitalservices.com.au`
- Dummy landing page with a Subscribe button
- Signup form that creates a test-paid subscription record
- Client dashboard based on the Fired The Agency dashboard layout
- Stripe-ready database fields and config placeholders

## Local Entry Point

Open through a PHP server with `public/` as the document root.

```text
php -S localhost:8000 -t public
```

For production, point the document root for `clients.smallbusinessdigitalservices.com.au`
to `public/`. The root `public/index.php` selects the correct brand from the
hostname, and shared assets remain available at `/shared/...`.

## Stripe

The first scaffold keeps payments in test mode with a fake paid subscription state.
Real Stripe Checkout can be added in `app/services/PaymentService.php` using the
keys and price ID in `app/config/app.php`.
