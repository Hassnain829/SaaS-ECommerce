# Phase 6C-5 — Steps 2–4 Implementation Notes

**Status:** Implemented + Step 4 defect corrections  
**Depends on:** Step 1 audit + frozen 6C-5A lifecycle  
**Follow-on:** `docs/fedex/PHASE_6C_5_STEPS_5_7_IMPLEMENTATION.md`

## Step 2 — Shared production operation foundation

| Component | Path |
|-----------|------|
| Operation guard | `Operations/FedExOperationGuard.php` |
| Safe exception mapping | `Operations/FedExSafeExceptionMapper.php` |
| API client ownership + idempotency header | `Operations/FedExMerchantApiClient.php` |
| Timeout / safe retries / sandbox copy fix | `Support/FedExHttpClient.php` |
| Config flags | `config/carriers.php`, `.env.example` |
| Throttle | `fedex-ops` in `AppServiceProvider` |

Hard rules enforced:

- No platform sandbox fallback for merchant ops
- Store ownership required
- Live environment requires `productionEnabled()`
- US/CA OD policy for Steps 3–4
- Stable `x-customer-transaction-id` per store/account/operation
- Model A ops require connected + enabled + exact active key + null disconnect/replace

## Step 3 — Address validation + service availability

- Production guard path on existing services (`enforceProductionGuard: true`)
- Normalized address + residential/classification for merchant review
- Ship-date validation
- Order UI: `resources/views/user_view/orders/partials/fedex_shipping_ops.blade.php`
- Routes: `orders.fedex.validate-address`, `orders.fedex.service-availability`

## Step 4 — Live negotiated rates

| Component | Path |
|-----------|------|
| Rate request DTO | `DTO/FedExShipmentRateRequest.php` |
| Payload factory production path | `FedExComprehensiveRatePayloadFactory::buildFromRequest` |
| Parser transit/surcharges/duties | `FedExComprehensiveRateResponseParser` |
| Merchant negotiated rates | `FedExNegotiatedRateService` |
| Checkout packages | `FedExCheckoutPackageBuilder` |
| Checkout resolver | `FedExCheckoutRateResolver` |
| Checkout wiring | `DeliveryOptionService` (origin route → packages → quote) |
| Order rates route | `orders.fedex.rates` |

Checkout rates remain **off** unless:

1. `FEDEX_CHECKOUT_RATES_ENABLED=true`
2. `FEDEX_OPS_NEGOTIATED_RATES_ENABLED=true`
3. Account `enabled_for_checkout`
4. Account capability `checkout_rates`
5. Active Model A connection (no platform fallback)
6. ACCOUNT rate + matching checkout currency

When live checkout rates are expected and FedEx is unavailable, the method is hidden — never replaced with platform credentials.

## Tests

- `tests/Feature/Phase6FedExProductionOpsSteps1to4Test.php`
- `tests/Feature/Phase6FedExStep4DefectCorrectionsTest.php`

## Explicitly still deferred (see Steps 5–7 doc for partial coverage)

Full manage/order wizard polish, admin diagnostics, live credentials, validation cleanup (Steps 8–16).
