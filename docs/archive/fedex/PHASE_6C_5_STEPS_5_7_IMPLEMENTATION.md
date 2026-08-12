# Phase 6C-5 — Steps 5–7 Implementation Notes

**Status:** Implemented (flags default **off**)  
**Depends on:** Step 4 defect corrections (guard, ACCOUNT rates, ship HTTP no-retry)

## Step 4 defect corrections (blocking)

| Defect | Fix |
|--------|-----|
| Weak operation guard | `FedExOperationGuard` requires connected + enabled + null disconnect/replace + exact `fedex_active_store_key` |
| LIST treated as negotiated | Negotiated/checkout paths require `rate_type=ACCOUNT`; LIST-only → `account_rate_unavailable` |
| Fixed checkout packages | `FedExCheckoutPackageBuilder` from cart qty/weight/dims |
| Wrong origin order | `DeliveryOptionService` routes fulfillment origin **first**, then packages, then rate |
| Incomplete cache key | Includes cart fingerprint, packages, origin, destination, residential, service, currency, ship date |
| Currency mismatch | Reject when FedEx currency ≠ checkout currency |
| Ship HTTP 502/503 retries | `FedExHttpClient` returns **1** attempt for `/ship/v1/*` |

Tests: `Phase6FedExStep4DefectCorrectionsTest.php`, updated `Phase6FedExProductionOpsSteps1to4Test.php`

Checkout rates remain **`FEDEX_CHECKOUT_RATES_ENABLED=false`** by default.

---

## Step 5 — Ship + labels + cancel/returns

| Component | Path |
|-----------|------|
| Purchase (idempotent) | `Operations/FedExShipmentPurchaseService.php` |
| Request builder | `Operations/FedExProductionShipRequestBuilder.php` |
| Label storage | `Operations/FedExLabelArtifactStore.php` |
| Cancel/void | `Operations/FedExShipmentCancelService.php` |
| Return labels | `Operations/FedExReturnLabelService.php` |

Behavior:

- Capability gate: `FEDEX_OPS_SHIP_LABELS_ENABLED` (default false)
- Persistent `Shipment` + `ShipmentPackage` + label files
- Store-scoped `idempotency_keys` + cache locks
- Uncertain 502/503/timeout → **do not** create shipment; block blind retry
- Label formats: PDF / PNG / ZPL
- Routes: `orders.fedex.shipments.create`, `orders.fedex.return-label`, `shipments.fedex.cancel`, `shipments.fedex.label.download`

## Step 6 — Customs + ETD

| Component | Path |
|-----------|------|
| Customs validation | `Operations/FedExCustomsValidationService.php` |
| ETD upload | `Operations/FedExProductionEtdUploadService.php` |

International ship requires validated commodities before create. ETD commercial invoice upload route: `orders.fedex.etd.upload`.

## Step 7 — Tracking

| Component | Path |
|-----------|------|
| Tracking API | `Operations/FedExProductionTrackingService.php` |
| Response parser | `Operations/FedExTrackingResponseParser.php` |
| Order sync | `Operations/FedExOrderTrackingSyncService.php` |
| Job | `Jobs/Carriers/FedEx/RefreshFedExShipmentTrackingJob.php` |
| Schedule | `fedex:refresh-tracking` every 15 minutes when tracking enabled |
| Customer page | `GET /t/{storeSlug}/fedex/{token}` |

Terminal shipment/order states are not overwritten incorrectly.

Capability gate: `FEDEX_OPS_TRACKING_ENABLED` (default false).

## Tests

- `tests/Feature/Phase6FedExStep4DefectCorrectionsTest.php`
- `tests/Feature/Phase6FedExProductionOpsSteps5to7Test.php`

## Still deferred

Steps 8–16: manage UI polish, full order wizard, admin diagnostics, full reliability suite, live credentials/preflight/smoke, validation tooling cleanup.
