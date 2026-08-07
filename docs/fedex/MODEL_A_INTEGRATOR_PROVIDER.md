# FedEx Model A — Official Integrator Provider

Phase **6C-4** implemented FedEx Model A as the **primary merchant-facing** FedEx connection path. Phase **6C-5A** implementation is **complete**. Phase **6C-5 Steps 1–4** add the shared production ops foundation, merchant address/service checks, and negotiated rates (order UI + gated checkout path). Live credentials remain unset; production flag remains false; controlled live smoke is an ops step. Labels/tracking remain deferred. Checkout rates require `FEDEX_CHECKOUT_RATES_ENABLED` plus account checkout capability. Model B remains available only when `FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED=true` **and** `APP_ENV` is `local|testing`.

See `docs/fedex/PHASE_6C_5A_LIVE_CONNECTION_HARDENING.md` and `docs/fedex/PHASE_6C_5_STEP1_FOUNDATION_AUDIT.md`.

## Architecture

| Role | Credentials | OAuth grant |
|------|-------------|-------------|
| Platform (integrator parent) | `.env` sandbox/live client id + secret | `client_credentials` |
| Merchant (child) | Encrypted `customer_key` / `customer_password` on `carrier_accounts` | `csp_credentials` |

FedEx billing stays between the merchant and FedEx. The platform does not buy postage or enable labels/rates/checkout until each capability is explicitly proven.

## Merchant flow

1. **Carrier wizard** → Connect FedEx account (`settings.shipping.fedex-integrator.start`)
2. **Ship-from origin** → creates `carrier_account_registration_sessions` row (`eula_required`)
3. **EULA** → scroll-to-bottom acceptance; version stored on session
4. **Account + address** → 9-digit account number + registration address
5. **Registration API** → `/registration/v2/address/keysgeneration` using parent OAuth
6. **MFA** (if required) → PIN / invoice steps via configurable endpoints
7. **Child OAuth verify** → credentials stored first (`credentials_issued`), then fresh `csp_credentials` OAuth
8. **Success** → `CarrierAccount` connected with `connection_model=integrator_provider`, encrypted child credentials + encrypted account number
9. **Manage** → verify / reconnect / disconnect (`settings.shipping.fedex-integrator.manage`)

## Configuration (`config/carriers.php` / `.env`)

Key flags:

- `FEDEX_DEFAULT_CONNECTION_MODEL=integrator_provider`
- `FEDEX_INTEGRATOR_MODEL_A_ENABLED=true`
- `FEDEX_INTEGRATOR_PRODUCTION_ENABLED=false` (gates live integrator onboarding; keep false until preflight + protected live keys)
- `FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED=false` (hides Model B wizard from merchants)
- `FEDEX_MFA_PIN_GENERATION_PATH`, `FEDEX_MFA_PIN_VALIDATION_PATH`, `FEDEX_MFA_INVOICE_VALIDATION_PATH` — **must be filled from FedEx portal docs** before MFA can complete in production validation

Preflight: `php artisan fedex:production-preflight` (never echoes secrets).

## Database

- `carrier_account_registration_sessions` — full registration state machine (+ `replacing_carrier_account_id` for reconnect)
- `carrier_accounts` — Model A fields plus `fedex_active_store_key`, encrypted account number/last4, `disconnected_at` / `replaced_at` / `replaced_by_carrier_account_id`
- `fedex_validation_artifacts` (optional) — redacted evidence rows
- Validation routes: `routes/fedex-validation.php` (local|testing only)

## MFA implementation note

If FedEx MFA endpoint paths or payloads are not yet confirmed in the integrator portal, the orchestrator surfaces a clear configuration error. **Do not invent MFA URLs.** Set the `FEDEX_MFA_*_PATH` env values in one place when FedEx provides them.

## Validation evidence export

```bash
php artisan fedex:validation-export --store=ID --carrier-account=ID --region=US --environment=sandbox
```

Produces a redacted zip under `storage/app/fedex-validation/` named `fedex-validation-bundle-{store_id}-{timestamp}.zip`.

Bundle contents (no placeholder JSON):

- `README.md`, `environment-summary.json`
- `registration/redacted-registration-session.json`
- `api-events/{action}.json` — latest redacted `CarrierApiEvent` per validation action
- `labels/` or `labels-not-generated.md`
- `notes/rate-quote-blocker.md`, `screenshots-required-checklist.md`, `test-case-summary.json`

Ship API sandbox tools (validate, label PDF/PNG/ZPL, cancel) use integrator child OAuth and are gated by:

- `FEDEX_SHIP_SANDBOX_LABEL_GENERATION_ENABLED`
- `FEDEX_SHIP_EVIDENCE_ENABLED`
- `FEDEX_INTEGRATOR_PRODUCTION_ENABLED` (live only)

HTTP 403 on rate quote or ship endpoints is recorded as **FedEx authorization blocked** (`fedex_authorization_blocked`) — an entitlement blocker for FedEx support, not a local payload defect.

No secrets, full account numbers, tokens, or raw label base64 are included in exports.

## Test baseline

FedEx integrator sandbox baseline spreadsheet:

- `docs/fedex/FedEx_Integrator_Test_Case_Baseline.xlsx`
- Parsed by `FedExTestCaseFixtureService` (OpenSpout) with fallback fixture for US account `700257037`

## Legacy integrator path

Accounts with `connection_mode=fedex_integrator` but **without** `connection_model=integrator_provider` / `fedex_integrator_account=true` continue to use the legacy platform registration + diagnostic connection test (local/testing only). This is separate from Model A.

## Routes

FedEx integrator routes are registered **before** wildcard `{carrier}` wizard routes to avoid route shadowing (`fedex-integrator` being captured as a carrier code).
