# Phase 6C-5 — Steps 5–12 Implementation Notes

**Status:** Steps 5–7 corrected + Steps 8–12 implemented (flags default **off**)  
**Depends on:** Step 4 approved

## Step 5–7 corrections (blocking)

| Defect | Fix |
|--------|-----|
| Crash-unsafe idempotency | `processing` row written **before** FedEx network call |
| Return label wrong | Swap shipper/recipient + `returnShipmentDetail.returnType=PRINT_RETURN_LABEL` |
| Delivered cancelable | Block delivered/returned/failed/cancelled; UI uses `isCancellable()` |
| Success without labels | Require usable encoded labels matching package count |
| Files inside DB TX | Persist shipment/items first; store labels **after** commit |
| Missing shipment items | Create `ShipmentItem` rows from order items |
| ETD not durable | `fedex_trade_documents` table linked to order/shipment |
| Dest country optional | Required for ETD upload |
| Failed upload files kept | Cleanup storage on failure |
| Fuzzy “deliver” mapping | Exact delivered / exception / out-for-delivery rules |
| Job swallows exceptions | Retryable transport failures rethrow for queue recovery |
| Tracking on replaced account | Prefer active Model A account |
| Account capabilities ignored | Guard enforces `capabilities.labels` / `tracking` |

Tests: `Phase6FedExProductionOpsSteps5to7Test.php` (10 cases)

## Step 8 — Manage UI

- Honest capability labels (platform + account)
- Account switches for checkout/labels/tracking
- Recent shipments + API activity (masked)
- Route: `settings.shipping.fedex-integrator.capabilities`
- Secrets / full account numbers never rendered

## Step 9 — Order fulfillment UI

Order FedEx panel supports:

1. Validate destination  
2. Service availability  
3. Negotiated rates  
4. Select service (from rate results when available)  
5. Create label (PDF/PNG/ZPL)  
6. Download label  
7. Void/cancel (terminal states blocked)  
8. Return label  
9. Track + public tracking link  
10. Customs/ETD upload + trade document / API status panel  

## Step 10 — Checkout

Live rates require checkout cart, routed origin, packages, ACCOUNT rate, currency match, checkout capability. Flag remains off by default. Unavailable FedEx live quotes hide the method (no platform sandbox fallback, no invented packages).

## Step 11 — Reliability

- Pre-network processing idempotency + cache locks
- No HTTP ship retries
- Tracking job retries on transport failures
- Throttle `fedex-ops`
- Store-scoped guards; encrypted credentials/account numbers (existing Model A)

## Step 12 — Admin diagnostics

- Route: `admin.fedex.diagnostics` (`/admin-fedex-diagnostics`)
- Admin sidebar: **FedEx ops**
- Failed connections, API events, uncertain ship ops, shipments, ETD docs
- No credentials / raw payloads

Tests: `Phase6FedExProductionOpsSteps8to12Test.php`

## Flags (keep false until live smoke)

- `FEDEX_CHECKOUT_RATES_ENABLED`
- `FEDEX_OPS_SHIP_LABELS_ENABLED`
- `FEDEX_OPS_TRACKING_ENABLED`
- `FEDEX_INTEGRATOR_PRODUCTION_ENABLED`

## Next (not started)

- Step 13 — broader focused suite expansion  
- Steps 14–15 — production credentials + controlled live smoke  
- Step 16 — validation tooling cleanup  
