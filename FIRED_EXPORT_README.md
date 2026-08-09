# Fired The Agency Setup Export

Upload the contents of this repository to the FTP `clients` directory for
`firedtheagency.com`.

The client portal runs from:

`https://clients.firedtheagency.com/`

Tracking parameters are preserved in generated URLs.

Example:

`https://clients.firedtheagency.com/?tracking=google-ad-1&thank_you=google`

Supported persistent parameters include `tracking`, `source`, `campaign`, `ref`, `thank_you`, `thankyou`, `utm_*`, `gclid`, `fbclid`, and `msclkid`.

After upload:

1. Copy `app/config/local.example.php` to `app/config/local.php`.
2. Fill in the database credentials.
3. Fill in Stripe keys and webhook secret.
4. Import `database/schema.sql` into the database if this is a new database.

Do not make `app/config/local.php` publicly downloadable.
