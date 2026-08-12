# FedEx Model A — Official Integrator Provider

Phase **6C-4** implemented FedEx Model A as the **primary merchant-facing** FedEx connection path. Phase **6C-5** production ops (address validation, service availability, negotiated rates, ship/labels, tracking, ETD) are live behind capability flags.

See `docs/archive/fedex/PHASE_6C_5A_LIVE_CONNECTION_HARDENING.md` and `docs/archive/fedex/PHASE_6C_5_STEP1_FOUNDATION_AUDIT.md` for historical hardening notes.

## Architecture

| Role | Credentials | OAuth grant |
|------|-------------|-------------|
| Platform (integrator parent) | `.env` sandbox/live client id + secret | `client_credentials` |
| Merchant (child) | Encrypted `customer_key` / `customer_password` on `carrier_accounts` | `csp_credentials` |

FedEx billing stays between the merchant and FedEx. The platform does not buy postage or enable labels/rates/checkout until each capability is explicitly enabled.

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
- `FEDEX_INTEGRATOR_PRODUCTION_ENABLED` — gates live integrator onboarding
- `FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED=false` (hides Model B wizard from merchants)
- `FEDEX_MFA_PIN_GENERATION_PATH`, `FEDEX_MFA_PIN_VALIDATION_PATH`, `FEDEX_MFA_INVOICE_VALIDATION_PATH` — fill from FedEx portal docs before MFA can complete live
- Ops flags: `FEDEX_OPS_*`, `FEDEX_CHECKOUT_RATES_ENABLED`, `FEDEX_OPS_SHIP_LABELS_ENABLED`, `FEDEX_OPS_TRACKING_ENABLED`

Preflight: `php artisan fedex:production-preflight` (never echoes secrets).

## Database

- `carrier_account_registration_sessions` — full registration state machine (+ `replacing_carrier_account_id` for reconnect)
- `carrier_accounts` — Model A fields plus `fedex_active_store_key`, encrypted account number/last4, `disconnected_at` / `replaced_at` / `replaced_by_carrier_account_id`
- `carrier_api_events` — production FedEx API audit trail (sanitized)
- `fedex_trade_documents` — production ETD upload records

Integrator certification / validation evidence tables and routes have been removed.

## MFA implementation note

If FedEx MFA endpoint paths or payloads are not yet confirmed in the integrator portal, the orchestrator surfaces a clear configuration error. **Do not invent MFA URLs.** Set the `FEDEX_MFA_*_PATH` env values when FedEx provides them.

## Production ops

Merchant production capabilities (behind flags + account capabilities):

- Address Validation API
- Service Availability
- Negotiated ACCOUNT rates / checkout live rates
- Ship / labels / void / returns
- BIV tracking
- Customs + ETD commercial invoice upload (`ETDPreShipment`)

Admin console: `/admin-fedex` tabs Overview, Connections, API events, Shipments, Trade documents.
