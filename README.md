# Fired The Agency Client Portal

PHP + mysqli client portal for Fired The Agency advertising campaign clients.

## Current Scope

- Client portal for `https://clients.firedtheagency.com/`
- Signup and login flow
- Signup form that creates a test-paid subscription record
- Fired The Agency branded client dashboard
- Stripe-ready database fields and config placeholders
- GitHub Actions deployment to the FTP `clients` subdirectory

## Local Entry Point

Open through a PHP server from the repository root.

```text
php -S localhost:8000
```

For production, GitHub Actions deploys this repository into the FTP `clients`
directory that serves `https://clients.firedtheagency.com/`.

Required GitHub repository secrets:

```text
FTP_SERVER
FTP_USERNAME
FTP_PASSWORD
FTP_CLIENTS_DIR
```

## Stripe

The first scaffold keeps payments in test mode with a fake paid subscription state.
Real Stripe Checkout can be added in `app/services/PaymentService.php` using the
keys and price ID in `app/config/app.php`.
