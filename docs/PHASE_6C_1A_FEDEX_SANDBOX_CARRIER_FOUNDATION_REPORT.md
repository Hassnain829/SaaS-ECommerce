# Phase 6C-1A — FedEx Sandbox Carrier Foundation Report

Generated: 2026-06-04  
Scope: Provider-neutral carrier architecture + FedEx sandbox connection foundation only

---

## Summary

Phase 6C-1A implements the **FedEx sandbox connection foundation** for store-scoped merchant FedEx accounts. This phase does **not** implement checkout live rates, label purchase, tracking sync jobs, pickup scheduling, or production/live FedEx mode.

---

## Implemented

| Area | Outcome |
|------|---------|
| Provider-neutral interface | `CarrierProviderInterface`, `CarrierProviderManager`, DTOs |
| FedEx sandbox config | `config/carriers.php` + `.env.example` placeholders |
| FedEx Account Registration | `FedExAccountRegistrationService` (sandbox child credentials) |
| FedEx OAuth token test | `FedExOAuthTokenService` (platform + merchant `csp_credentials`) |
| Encrypted merchant credentials | `CarrierAccount` encrypted credentials + hidden from arrays/views |
| Store-scoped FedEx accounts | CRUD/test/disable routes with store + permission checks |
| Carrier API event logs | `carrier_api_events` table + masked `CarrierApiEventLogger` |
| Shipping & Delivery UI | FedEx sandbox card, account list, test/disable, recent API activity |
| Tests | `Phase6FedExSandboxCarrierFoundationTest` (13 tests) |

---

## Schema changes

### Extended `carrier_accounts`

- `provider`, `environment`, `connection_mode`, `billing_owner`
- `provider_account_number`, `capabilities`
- `connection_status`, `last_verified_at`, `last_error_code`, `last_error_message`
- Reused existing `credentials_encrypted` (encrypted array)

### New `carrier_api_events`

Store-scoped audit log for FedEx API actions (`account_registration`, `oauth_token`, `test_connection`) with masked summaries only.

---

## Security

- Platform FedEx keys live in `.env` / hosting secrets only — never committed
- Merchant customer key/password encrypted via Laravel `encrypted:array`
- `credentials_encrypted` hidden from model serialization
- No secrets/tokens in `carrier_api_events`
- Account numbers masked (last 4) in UI and logs
- FedEx HTTP calls server-side only (never from Blade/JS)

---

## Merchant UX

Shipping & Delivery page includes:

- FedEx sandbox section with **Sandbox only** badge
- Add/update account form (defaults display name to “FedEx sandbox account”)
- Connection status: Not connected / Connected / Failed / Disabled
- **Test connection** and **Disable** actions
- Recent FedEx API activity panel (safe fields only)
- Friendly message when platform FedEx config is missing
- Local/testing developer diagnostics line (present/missing only — no key values)

**Not shown:** Buy label, Generate label, Live rates, Production enabled, fake Connected badges

---

## Explicitly deferred

### 6C-1B — Sandbox rate quotes
- FedEx sandbox rate quotes
- Package builder
- Carrier rate quote records
- Order/shipment quote UI

### 6C-1C — Label purchase
- FedEx label purchase
- Shipment label records
- Label PDF/ZPL storage
- Idempotent label purchase

### 6C-1D — Tracking sync
- FedEx tracking sync jobs
- Status mapping
- Tracking retry jobs

### 6C-1E — Production/live
- Production/live FedEx keys workflow
- Production validation
- Live label guardrails

### 6C-2+ — Other carriers & automation
- UPS, DHL, USPS, Canada Post
- Carrier fallback rules
- Cheapest/fastest/balanced automation
- Pickup scheduling

---

## Key files

| File | Role |
|------|------|
| `config/carriers.php` | FedEx env config |
| `app/Services/Carriers/*` | Provider contracts + event logger |
| `app/Services/Carriers/FedEx/*` | FedEx adapter services |
| `app/Http/Controllers/ShippingSettingsController.php` | FedEx store/test/disable |
| `resources/views/user_view/shippingAutomation.blade.php` | Merchant UI |
| `tests/Feature/Phase6FedExSandboxCarrierFoundationTest.php` | Foundation tests |

---

## Commands (verification)

```text
composer dump-autoload
php artisan optimize:clear
php artisan migrate
php artisan test --filter=Phase6FedExSandboxCarrierFoundationTest
php artisan test --filter=Phase6ManualFulfillmentTest
php artisan test --filter=Phase6CheckoutDeliveryMethodsTest
php artisan test --filter=Phase6NearestEligibleOriginRoutingTest
php artisan test --filter=ExternalManagedChannelModeTest
php artisan test
npm.cmd run build
```

### FedEx Credential Registration endpoint (2026-06 patch)

- **Deprecated (do not use):** `POST /irc/v2/customerkeys`, `/registration/v1/address/keysgeneration`
- **Current default:** `POST /registration/v2/address/keysgeneration` (FedEx Credential Registration API — address validation key generation)
- Configurable per environment:
  - `FEDEX_SANDBOX_ACCOUNT_REGISTRATION_PATH`
  - `FEDEX_LIVE_ACCOUNT_REGISTRATION_PATH`

Connection test is split into logged steps: `platform_oauth_token` → `account_registration` → `merchant_oauth_token`.

If platform OAuth succeeds but registration fails, merchants see: *FedEx platform credentials are valid, but account registration failed.*

`carrier_api_events` records masked `request_summary.endpoint`, `response_summary.http_status`, and `response_summary.fedex_transaction_id`.

---

**FedEx sandbox connection foundation complete.** Labels, checkout live rates, and tracking sync remain deferred to 6C-1B–1E.
