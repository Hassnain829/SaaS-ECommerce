# Phase 11 — Notifications and Communication

**Status:** Complete — enterprise signed-off (including tenant-recipient service guards)  
**Date:** 2026-07-30  
**Scope:** 11A foundation, 11B merchant email for existing domains, 11C customer transactional emails + commit isolation, retry, interrupted-worker recovery, and store-membership enforcement inside notification services

## Goal

Replace the static notifications mock with a store-scoped, preference-aware, idempotent notification system covering in-app and email channels across live commerce domains — without ever rolling back commerce/payment/inventory transactions.

## What shipped

### Schema
- `notifications` — store-scoped delivery log / in-app center rows (`StoreNotification` model)
- `notification_preferences` — per store/user/channel event toggles (`quiet_hours` column retained unused for compatibility)

### Services
- `NotificationCommitBoundary` — `DB::afterCommit()` when inside a transaction; immediate otherwise; exceptions logged and swallowed
- `NotificationDispatcher` — create/dedupe (`UniqueConstraintViolationException` only), store/user/customer emit, atomic email retry
- `NotificationPreferenceService` — channel + event-group preferences (no quiet-hours delivery gating)
- `NotificationQueryService` — center feed, merchant failed emails, store-scoped customer email failures
- `CommerceNotificationEmitter` — domain payloads; reloads models by ID after commit
- `LowStockNotifier` — captures only variant ID + optional scalar store ID before the boundary; rejects cross-store misrouting

### Merchant UX
- Dynamic [`resources/views/user_view/notifications.blade.php`](../../resources/views/user_view/notifications.blade.php)
- Mark read / mark all read / preference save
- Merchant failed-email retry (shows stored error text)
- Owner/manager (or `orders.manage`) **Customer email failures** section + atomic retry
- Topbar unread badge via view composer
- Channel label: **Email alerts** (not digest)

### Delivery / retry / recovery behavior
- `SendNotificationEmailJob` (`ShouldBeUnique` + `WithoutOverlapping`, `uniqueFor` / expireAfter **900s** ≥ sum of backoffs `[30, 120, 300]`)
- Atomic claim: `queued` → `sending` before `Mail::send`; losers on attempt 1 remain a no-op
- Success: `sending` → `sent` (never downgraded)
- Retryable failure: increment `attempts`, keep error, return to `queued`, rethrow for Laravel backoff
- Terminal: mark `failed` on final job attempt; `failed()` does **not** double-increment attempts
- Missing recipient fails immediately **after** the global attempts cap check
- Global cap: `NotificationDispatcher::MAX_EMAIL_ATTEMPTS` (5) for automatic and manual retries
- **Interrupted-worker recovery:** if a later queue attempt/redelivery still sees `sending`, atomically mark `failed` with  
  `Email delivery outcome is uncertain after an interrupted worker. Review before retrying.`  
  — no automatic `Mail::send`; visible and manually retryable under the attempts cap; attempts not incremented on recovery
- Deduped by `(store_id, type, channel, dedupe_key, recipient_key)`

### Customer action links
- Order / refund / return customer emails omit merchant-authenticated `action_url`
- Shipment customer emails may include `tracking_url` only when it is a valid `http`/`https` URL

### Domain hooks wired
| Event | Source |
|-------|--------|
| `order.created` | CheckoutConversion, ExternalOrderSync, ManualOrderConversion (+ customer confirmation) |
| `payment.failed` | CheckoutConversion |
| `inventory.low` | InventoryAdjustmentService → LowStockNotifier |
| `import.completed` / `import.failed` | ProductImportProcessor |
| `return.*` | ReturnService (+ customer status emails) |
| `refund.completed` / `refund.failed` | RefundService (+ customer confirmation on success) |
| `exchange.created` / `exchange.completed` | ExchangeService |
| `shipment.shipped` / `delivered` / `tracking_updated` | ShipmentService (+ customer emails) |
| `security.login_new_device` | UserSessionTracker |

### Reserved (preference-ready, emitters deferred)
- `webhook.failed` — **Phase 9** dependency (integrations / webhooks)
- `billing.issue` — **Phase 10** dependency (SaaS billing)

## Acceptance gate

| Criterion | Result |
|-----------|--------|
| Preferences respected | Pass |
| Quiet hours / digest | Removed from delivery path and UI (column unused) |
| No duplicate messages on retry | Pass |
| Notification failure cannot roll back commerce | Pass |
| Failed merchant/customer email visible + retryable | Pass |
| Automatic email retries functional | Pass |
| Stale `sending` recovery | Pass — attempt ≥ 2 → failed + uncertain message; attempt 1 concurrent → no-op |
| Sent never downgraded | Pass |
| Attempts never exceed MAX | Pass |
| Focused notification tests | Pass — **35** tests in `tests/Feature/Notifications/*` |
| Phase 7 suites | Pass — **54** tests |
| Full repository suite | **Not green** — see honesty note |

## Honesty note — full suite

As of 2026-07-30, `php artisan test` reports **1502 passed / 2 skipped / 5 failed**.

The five failures are **unrelated** to Phase 11 notifications (Phase 5 order-event copy assertions and Phase 6 FedEx UI wording). This report marks Phase 11 **enterprise signed-off for its scoped acceptance criteria** and does **not** claim repository full-suite green......

### Tenant-recipient enforcement (2026-07-30)

- `NotificationDispatcher::notifyUser()` rejects inactive users and non-members (`[]`, no rows/prefs/jobs).
- `NotificationDispatcher::retryEmail(Store, …)` requires matching `store_id` on the atomic claim.
- `NotificationPreferenceService::forUser()` throws `AuthorizationException` for foreign/inactive recipients (no preference row).
- Customer `notifyCustomer()` remains membership-free.
- Focused coverage: `NotificationTenantRecipientGuardTest`

## Explicit non-goals (unchanged)
- Outbound webhook product (Phase 9)
- SaaS billing invoice emails (Phase 10)
- SMS/WhatsApp
- Live carrier tracking emails
- Digest scheduling / delayed quiet-hours delivery
- Outbox framework (Phase 9)
- Admin SMTP/Twilio persistence UI (Phase 12)

## Key paths

- Migration: `database/migrations/2026_07_28_140000_create_phase11_notification_tables.php`
- Boundary: `app/Services/Notifications/NotificationCommitBoundary.php`
- Job: `app/Jobs/SendNotificationEmailJob.php`
- Controller: `app/Http/Controllers/Store/NotificationController.php`
- Support catalog: `app/Support/NotificationEvent.php`
- Tests: `NotificationCenterTest`, `NotificationDomainEmitTest`, `NotificationCorrectionTest`, `NotificationEmailRetryTest`, `NotificationTenantRecipientGuardTest` (35 total)
