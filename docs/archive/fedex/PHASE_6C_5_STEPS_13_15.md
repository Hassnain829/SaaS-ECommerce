# Phase 6C-5 — Steps 13–15

**Status:** Integrity blockers corrected; Step 13 tests extended; Steps 14–15 gated (repo defaults stay off)  
**Depends on:** Crash-safe reservation, partial-ship UI, cancel integrity, quote binding, customs UI, staged smoke

## Integrity corrections applied before Step 13

1. Production ops never fall back to sandbox (`FedExOperationGuard`)
2. Pre-network `pending` shipment + `ShipmentItem` reservation (release on fail; retain on uncertain)
3. `shipments.direction` = `outbound|return`; returns excluded from fulfillment math
4. Tracking uses `FulfillmentStatusService::recalculateAndPersist()`
5. Outbound labels require bound ACCOUNT `CarrierRateQuote` (countries, residential, ship date, packages)
6. Trade documents verified store/order/account/status/countries; selectable in order UI
7. Label storage verifies `put` + exists + size; multi-package downloads via `?index=`
8. Capability POST rejects when global flags are off; checkout enable requires both
9. Partial outbound/return item selection (checkbox + qty); returns require explicit items
10. Cancel uses original billing `carrier_account_id`; fulfillment recalculated after cancel
11. International customs commodity fields on order FedEx panel

## Step 13 — Focused tests

| Suite | Coverage |
|-------|----------|
| `Phase6FedExProductionOpsSteps1to4Test` | Ops foundation / checkout rates |
| `Phase6FedExStep4DefectCorrectionsTest` | Guard / ACCOUNT rates / ship HTTP |
| `Phase6FedExProductionOpsSteps5to7Test` | Ship/return/cancel/tracking |
| `Phase6FedExProductionOpsSteps8to12Test` | Manage UI / order panel / admin / flags |
| `Phase6FedExProductionIntegritySteps13Test` | Reservation, over-ship, return isolation, quote bind, ETD, storage, cancel, preflight |

Run:

```bash
php artisan test --filter="Phase6FedExProductionOpsSteps|Phase6FedExStep4Defect|Phase6FedExProductionIntegritySteps13"
```

## Step 14 — Protected production configuration (ops only)

Do **not** commit live secrets. On a protected host:

1. Set `FEDEX_ENVIRONMENT=live`
2. Set live parent Client ID / Secret
3. Confirm `FEDEX_LIVE_BASE_URL=https://apis.fedex.com`
4. Enable Model A; keep Model B / validation / platform fallback off
5. Set official MFA paths (`FEDEX_MFA_*`) and BIV path (`FEDEX_BASIC_INTEGRATED_VISIBILITY_PATH`)
6. Set `FEDEX_INTEGRATOR_PRODUCTION_ENABLED=true` only after Step 13 green
7. Keep capability flags **false** in repo defaults; enable only during staged smoke on the protected host
8. Migrate, clear caches
9. Run `php artisan fedex:production-preflight` — must PASS including `FEDEX_ENVIRONMENT=live` and MFA paths

## Step 15 — Controlled live smoke (staged activation)

Use one internal merchant account. Stop on any credential, billing, duplicate postage, or cross-store issue.

**Repo defaults remain off.** On the protected host only, enable flags in stages:

### Stage A — Rates (labels/tracking/checkout still off)

1. Connect live Model A account  
2. Verify child OAuth  
3. Validate address  
4. Service availability  
5. Negotiated ACCOUNT rate  

### Stage B — Labels

6. Enable `FEDEX_OPS_SHIP_LABELS_ENABLED=true` **and** the account-level ship-labels capability  
7. Re-run `fedex:production-preflight` (ship create/cancel path checks must PASS)  
8. Create one outbound shipment from bound quote + selected remaining qty (+ customs if CA)  
9. Download label(s); confirm shipment + `ShipmentItem` rows  
10. Create return label with **explicit** return items  
11. Cancel/void if allowed; confirm fulfillment status recalculated  

### Stage C — Tracking

12. Enable `FEDEX_OPS_TRACKING_ENABLED=true` **and** account tracking capability  
13. Re-run preflight (BIV path must PASS)  
14. Refresh tracking for the live shipment  

### Stage D — Checkout rates

15. Enable `FEDEX_CHECKOUT_RATES_ENABLED=true` **and** account checkout capability  
16. Checkout rate smoke against the same live account  

### Stage E — Reconnect hygiene

17. Reconnect + verify  
18. Confirm rates/labels/tracking still operational  

## Flags remain off in repo defaults

- `FEDEX_CHECKOUT_RATES_ENABLED=false`
- `FEDEX_OPS_SHIP_LABELS_ENABLED=false`
- `FEDEX_OPS_TRACKING_ENABLED=false`
- `FEDEX_INTEGRATOR_PRODUCTION_ENABLED=false`
- `FEDEX_ENVIRONMENT=sandbox`

**Do not start live smoke until Steps 13–14 are green and staged flags are enabled deliberately on the protected host.**
