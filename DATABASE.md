# Database

## Production Database

- Database: `smallbusinessd_dbx`
- Host: `localhost`
- Username: `smallbusinessd_dbu`

The password is intentionally not stored in this Markdown file or committed to GitHub.
For deployment, the private password belongs in ignored local config:

```text
app/config/local.php
```

## Import File

The canonical SQL schema is stored at:

```text
database/schema.sql
```

## Tables

- `brands`
- `businesses`
- `users`
- `subscriptions`
- `campaigns`
- `campaign_results`

## Stripe-Ready Fields

The `subscriptions` table includes:

- `stripe_customer_id`
- `stripe_checkout_session_id`
- `stripe_subscription_id`
- `stripe_payment_intent_id`

These are currently placeholders for the real Stripe Checkout integration.
