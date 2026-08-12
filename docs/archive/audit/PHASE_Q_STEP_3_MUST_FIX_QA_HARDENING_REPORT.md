# Phase Q Step 3 — Must-Fix QA Hardening Report

Generated: 2026-05-24  
Branch: `main`  
Scope: QA-001 (external order dedup) + QA-007 (6C-0A routing test hardening) + **Step 3C** (strict external order identity + QA file cleanup)

---

## Summary

| Finding | Status | Outcome |
|---------|--------|---------|
| **QA-001** — External order dedup/idempotency | **Fixed** | Step 3: dedup identity validation + `external_order_id` column; Step 3C: **external_order_id or external_order_number required**; Idempotency-Key optional only |
| **QA-007** — Phase 6C-0A routing edge cases | **Fixed** | 9 negative/edge-case routing tests added; no routing feature changes required |

**Phase 6C-1 carrier sandbox was not implemented.**

---

## Phase Q Step 3C — Strict external order identity (2026-05-24)

### Rule

- **Required:** `external_order_id` **or** `external_order_number` on every external order create.
- **Optional:** `Idempotency-Key` header for additional replay/idempotency protection when paired with a stable external identity.
- **Rejected:** Idempotency-Key only (no external order id/number).

Validation message:

> External order sync requires external_order_id or external_order_number. Idempotency-Key is supported as replay protection but cannot be the only order identity.

This closes the concurrency gap where two simultaneous Idempotency-Key-only requests could create duplicate orders before the key is persisted.

### Changes

1. **`ExternalOrderSyncController`** — `assertExternalOrderIdentityPresent()` replaces Step 3 `assertDedupIdentityPresent()`; no early return on Idempotency-Key alone.
2. **`ExternalOrderSyncService`** — unchanged; store/channel-scoped dedup still prefers `external_order_id`, then `external_order_number`.
3. **Tests** — `EnterpriseQaExternalOrderDedupHardeningTest` expanded to 10 tests (8 identity/dedup + 2 inventory owner regressions).
4. **QA artifacts** — moved from project root to `docs/audit/` (see below).

### QA artifact locations

| File | Location |
|------|----------|
| Audit bundle, gap report, risk register, Step 2 notes, command outputs | `docs/audit/` |
| Dev storefront command outputs | `docs/audit/dev-test-storefront/` |
| Regeneration helpers | `tools/generate_qa_audit_bundle.py`, `tools/generate_qa_command_outputs.py` (output to `docs/audit/`) |

---

## QA-001 — External order dedup/idempotency

### Problem (Step 2)
External order sync could create duplicate orders when integrators omitted `Idempotency-Key`, `external_order_id`, and `external_order_number` (NULL bypassed DB unique index).

### Fixes applied

1. **`ExternalOrderSyncController`**
   - Step 3: dedup identity validation; Step 3C: **`assertExternalOrderIdentityPresent()`** — requires `external_order_id` or `external_order_number`; Idempotency-Key alone returns 422.
   - Message: *External order sync requires external_order_id or external_order_number. Idempotency-Key is supported as replay protection but cannot be the only order identity.*
   - Added validation for `external_order_id` payload field.

2. **`ExternalOrderSyncService`**
   - Extracted `resolveExistingExternalOrder()` for store/channel-scoped dedup.
   - Priority: `external_order_id` first, then `external_order_number` (aligned with prompt).
   - Same request hash → safe replay (`created: false`); different hash → 409 conflict.

3. **Migration `2026_05_31_010000_add_external_order_id_to_orders_table.php`**
   - Added `orders.external_order_id` column.
   - Unique index: `(store_id, order_source, channel, external_order_id)`.

4. **`Order` model** — `external_order_id` added to fillable.

### Tests added
`tests/Feature/EnterpriseQaExternalOrderDedupHardeningTest.php` (10 tests after Step 3C):
- Reject Idempotency-Key-only (no external id/number)
- Reject without any external order identity
- Idempotency-Key + external_order_id → single order on retry
- Idempotency-Key + external_order_number → single order on retry
- external_order_id only → deduped on retry
- external_order_number only → deduped on retry
- Same external_order_id across different stores
- Same external_order_number across different stores
- External inventory owner skips platform stock
- Platform inventory owner deducts/routes once on retry

### Regression
`Phase5ExternalCheckoutSyncTest` — **8 passed** (existing payloads include `external_order_number`).

---

## QA-007 — Phase 6C-0A routing hardening tests

### Problem (Step 2)
Only 5 dedicated routing tests; missing negative cases for pickup, reroute failure, service-area vs priority, stock precedence, and no-origin errors.

### Fixes applied
**No production routing logic changes required.** Existing `FulfillmentOriginRouter` and `CheckoutShippingService` behavior passed all new tests after correct test setup (e.g. pickup-only locations excluded from initial delivery routing).

### Tests added
`tests/Feature/EnterpriseQaOriginRoutingHardeningTest.php` (9 tests):
1. Cross-store pickup location rejected
2. Pickup location without stock rejected
3. Multiple pickup locations require explicit selection
4. Failed reroute preserves original reservation
5. Successful reroute moves reservation + snapshot + event
6. Service-area specificity wins before routing priority
7. Stock availability beats stronger service-area match
8. No eligible origin → clean validation error (no checkout/reservation/payment intent)
9. Merchant UI/docs do not claim physical nearest (roadmap defers 6C-0B)

### Regression
`Phase6NearestEligibleOriginRoutingTest` — **5 passed**

---

## Commands run

```text
php artisan migrate --force                          OK
php artisan migrate:rollback --step=1                  OK
php artisan migrate --force                          OK
php artisan test --filter=EnterpriseQaExternalOrderDedupHardeningTest  10 passed (Step 3C)
php artisan test --filter=EnterpriseQaOriginRoutingHardeningTest        9 passed
php artisan test --filter=Phase5ExternalCheckoutSyncTest                8 passed
php artisan test --filter=Phase6NearestEligibleOriginRoutingTest      5 passed
php artisan test --filter=ExternalManagedChannelModeTest               22 passed
php artisan test                                                    445 passed (2200 assertions)
```

**Build:** Not run (no Blade/JS/Vite changes).

---

## Files changed

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/ExternalOrderSyncController.php` | Step 3 dedup validation; Step 3C strict external identity |
| `app/Services/ExternalOrderSyncService.php` | `external_order_id` dedup + storage |
| `app/Models/Order.php` | Fillable `external_order_id` |
| `database/migrations/2026_05_31_010000_add_external_order_id_to_orders_table.php` | New |
| `tests/Feature/EnterpriseQaExternalOrderDedupHardeningTest.php` | Step 3 + Step 3C hardening |
| `tests/Feature/EnterpriseQaOriginRoutingHardeningTest.php` | New |
| `docs/audit/PHASE_Q_STEP_3_MUST_FIX_QA_HARDENING_REPORT.md` | Step 3 + 3C report |
| `docs/audit/ENTERPRISE_QA_GAP_REPORT.md` | Step 3/3C remediation notes |
| `docs/audit/ENTERPRISE_QA_RISK_REGISTER.md` | Mitigation status |
| `tools/generate_qa_audit_bundle.py` | Output path → `docs/audit/` |
| `tools/generate_qa_command_outputs.py` | Output path → `docs/audit/` |
| `ENTERPRISE_PROJECT_CONTEXT.md` | Step 3 cross-reference |
| `ENTERPRISE_ROADMAP_2026.md` | Step 3 gate before 6C-1 |

---

## Remaining deferred (not Step 3 scope)

- Phase 6C-1 carrier sandbox, live rates, labels, tracking sync
- True physical nearest (6C-0B lat/lng/geocoding)
- DashboardController refactor (QA-006)
- Read-route permission middleware (QA-004)
- Stripe webhook event-id dedup table (QA-002)
- Manager vs owner settings RBAC decision (QA-005)

---

## Verdict

**Step 3 and Step 3C complete.** External order creation requires a stable external identity (`external_order_id` or `external_order_number`). Platform is cleared to begin Phase 6C-1 carrier sandbox work with hardened external order identity and expanded 6C-0A routing test coverage.
