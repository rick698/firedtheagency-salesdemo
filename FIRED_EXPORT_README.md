# Fired The Agency Setup Export

Upload the contents of this zip to the root of `firedtheagency.com`.

The setup flow runs from:

`https://firedtheagency.com/setup.php`

Tracking parameters are preserved in generated URLs when using `setup.php`.

Example:

`https://firedtheagency.com/setup.php?tracking=google-ad-1&thank_you=google`

Supported persistent parameters include `tracking`, `source`, `campaign`, `ref`, `thank_you`, `thankyou`, `utm_*`, `gclid`, `fbclid`, and `msclkid`.

After upload:

1. Copy `app/config/local.example.php` to `app/config/local.php`.
2. Fill in the database credentials.
3. Fill in Stripe keys and webhook secret.
4. Import `database/schema.sql` into the database if this is a new database.

Do not make `app/config/local.php` publicly downloadable.
