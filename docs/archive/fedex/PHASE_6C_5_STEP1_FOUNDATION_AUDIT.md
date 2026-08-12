# Phase 6C-5 — Step 1 Foundation Audit

**Status:** Complete (inventory only — no 6C-5A rewrites)  
**Date:** 2026-08-07  
**Scope:** Lock Model A connection foundation; map preserve vs build for Steps 2–16.

---

## Verdict

Phase **6C-5A** Model A connection lifecycle is **production-ready and frozen**. Steps 2–4 promote existing validation/sandbox Operations clients into a **store-scoped production operation layer** without duplicating HTTP/OAuth stacks.

Live credentials, `FEDEX_INTEGRATOR_PRODUCTION_ENABLED`, and controlled live smoke remain **ops gates (Steps 14–15)**. Do not enable them in Steps 1–4.

---

## Preserve (do not rewrite)

| Area | Canonical paths |
|------|-----------------|
| Registration + reconnect TX | `FedExIntegratorRegistrationOrchestrator` |
| Verify / resume / reconnect / disconnect | `FedExMerchantConnectionLifecycleService` |
| Child / parent OAuth | `Auth/FedExIntegratorChildOAuthService`, `FedExIntegratorParentOAuthService` |
| Config gates / preflight | `Support/FedExConfig`, `FedExProductionPreflightCommand` |
| Route isolation | `routes/carriers.php`, `routes/fedex.php`, `routes/usps.php`, `routes/fedex-validation.php` |
| Encrypted account + active key | migrations `2026_08_06_010000_*`, `020000_*`; `CarrierAccount` Model A fields |
| Merchant-safe connection failures | `FedExConnectionFailurePresenter` |
| Manage connection UI contract | `fedex_integrator/manage.blade.php` (verify/resume/reconnect/disconnect) |
| Lifecycle / isolation tests | `Phase6FedExLifecycle*`, `Phase6FedExProductionRouteIsolation*`, `Phase6FedExConnectionSecurity*` |

---

## Inventory map

### Services (`app/Services/Carriers/FedEx/`)

| Layer | Role | Step 1 note |
|-------|------|-------------|
| Auth/* | OAuth | Preserve |
| Connection/* | Model A onboarding + lifecycle | Preserve |
| Operations/* | Address, rates, ship, track, ETD | **Promote** for production (were validation/test heavy) |
| Validation/* | Integrator evidence | Keep; cleanup = Step 16 |
| Support/* | Config, HTTP, provider | Harden in Step 2 |
| Presenters/* | UI mapping | Extend carefully |

### Controllers

| Path | Role |
|------|------|
| `Carrier/Connection/FedExIntegratorConnectionController` | Model A connect + manage |
| `Carrier/Operations/FedExCarrierTestController` | Sandbox/test tools (Model B / local) |
| `Carrier/Validation/*` | Validation workspace (local\|testing) |
| `Settings/FedExShippingSettingsController` | Model B settings |
| `Settings/ShippingSettingsController` | Shared shipping UI; blocks Model A destroy |

### Models / tables

| Model | Use |
|-------|-----|
| `CarrierAccount` | Model A account + encrypted secrets |
| `CarrierAccountRegistrationSession` | Registration SM |
| `CarrierApiEvent` | Auditable API events |
| `Shipment` / `ShipmentPackage` / `CarrierRateQuote` | Fulfillment + quote persistence (reuse) |
| `FedExValidationArtifact` (+ related) | Evidence only |

### Views

| Area | Path |
|------|------|
| Integrator flow | `resources/views/user_view/fedex_integrator/*` |
| Merchant card | `shipping/partials/fedex_merchant_card.blade.php` |
| Validation (debt) | `fedex_validation/*`, Advanced certification tools |

### Config / env

- `config/carriers.php` → `fedex.*`
- Flags: Model A on, production off, Model B off, validation mode local-only, platform fallback off
- API paths: address resolve, packageandserviceoptions, rates/quotes, comprehensiverates/quotes

---

## Capability status after Step 1

| Capability | Status |
|------------|--------|
| Connect / verify / resume / reconnect / disconnect | **PRODUCTION-READY** |
| Shared merchant API client + OAuth refresh | **PARTIAL** → Step 2 harden |
| Address validation / service availability | **VALIDATION/TEST** → Step 3 |
| Negotiated comprehensive rates | **VALIDATION/FIXTURE** → Step 4 |
| Checkout live rates | Stub only → Step 4 wire + flags |
| Labels / cancel / returns / customs / tracking jobs | Deferred Steps 5–7 |
| Admin diagnostics / validation cleanup | Steps 12 / 16 |

---

## Architecture flaws found (fix in Steps 2–4)

1. **Fixture-only comprehensive rates** — `FedExComprehensiveRateQuoteService` depends on validation fixtures; production needs a real shipment request DTO path.
2. **No production operation guard** — API client checks credential shape but not store ownership, active Model A, country policy, or capability flags in one place.
3. **No idempotency contract for ops** — HTTP has `x-customer-transaction-id`, but callers do not pass stable store/operation keys.
4. **Transport copy hardcodes “sandbox”** — `FedExHttpClient` failure text says sandbox even for live.
5. **Retry limited to ship paths** — Safe read-ish POSTs (address / availability / rates) should also retry 502/503.
6. **Checkout `carrier_calculated_later`** — placeholder flat rate; must never fall back to platform FedEx credentials.
7. **Manage UI claims all ops disabled** — After Steps 3–4, capabilities display must reflect address/rates ops flags honestly (labels/tracking still off).

---

## FedEx Developer Portal alignment (Steps 2–4)

| API | Path (configured) | Notes |
|-----|-------------------|-------|
| Address Validation | `/address/v1/addresses/resolve` | US/CA residential/business classification; suggestions only — not deliverability |
| Service Availability | `/availability/v1/packageandserviceoptions` | Service + packaging for OD pair; Rate API does **not** prove availability |
| Comprehensive Rates | `/rate/v1/comprehensiverates/quotes` | ACCOUNT + LIST; `returnTransitTimes`; duties/taxes when commodities provided |
| Rate quotes (standard) | `/rate/v1/rates/quotes` | Keep for diagnostics; production merchant path prefers comprehensive |

---

## Explicit non-goals for Steps 1–4

- Live credential injection / production flag flip
- Ship / label / cancel / returns / ETD production purchase
- Tracking jobs / customer tracking page
- Validation tooling removal
- UPS / DHL / USPS production labels

---

## Next

- **Step 2** — Shared production operation foundation  
- **Step 3** — Address validation + service availability + merchant review UI  
- **Step 4** — Live negotiated rates (fulfillment + gated checkout)
