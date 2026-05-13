# Security rotation required

If this project or a distribution ZIP was shared with real credentials, assume compromise and rotate everything below.

## Application

- **APP_KEY** — Run `php artisan key:generate` on every deployed environment after a leak. This invalidates existing sessions and encrypted data tied to the old key.

## Payments (Stripe)

- **STRIPE_KEY** (publishable)
- **STRIPE_SECRET**
- **STRIPE_WEBHOOK_SECRET**
- **STRIPE_CONNECT_WEBHOOK_SECRET**
- **STRIPE_CONNECT_CLIENT_ID** (if used)

Rotate in the [Stripe Dashboard](https://dashboard.stripe.com/) and update environment variables. Re-create webhook endpoints if endpoint secrets were exposed.

## Developer storefront API token

- Per-store **developer storefront token** (hashed in `stores.developer_storefront_token_hash`). Revoke and re-issue from the merchant dashboard for each affected store.

## Mail and third-party integrations

If any of these were present in a leaked `.env`, rotate them and update configuration:

- Mail provider passwords / API keys (SMTP, Postmark, Resend, etc.)
- **AWS_ACCESS_KEY_ID** / **AWS_SECRET_ACCESS_KEY**
- **SLACK_BOT_USER_OAUTH_TOKEN**
- Any logging or monitoring tokens (e.g. **LOG_SLACK_WEBHOOK_URL**)

## Database

- Change **DB_PASSWORD** and database user passwords if they appeared in a leak.

## After rotation

1. Deploy new secrets only via your host’s secret manager or encrypted env, not via chat or email.
2. Ensure `.env`, `.env.local`, and similar files are not committed (see `.gitignore` and `docs/RELEASE_CHECKLIST.md`).
