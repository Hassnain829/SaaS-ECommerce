# Phase 6C-5A — Live merchant connection hardening

**Status:** Phase 6C-5A implementation complete (focused lifecycle, migration, route-isolation and regression tests green).

## Completion notes

* Live credentials remain unset in repo defaults
* `FEDEX_INTEGRATOR_PRODUCTION_ENABLED` remains `false`
* Controlled live connection smoke remains an **operations** step
* Rates / labels / tracking / checkout remain disabled
* Phase 6C-5B–5E remain deferred
* Validation storage artifacts under `storage/app/fedex-validation` were intentionally removed and externally backed up — not restored
* Validation tooling cleanup / admin migration remains a future separate phase

## Scope completed

### Batch 1 — Production-safe foundation
- Sandbox default; Live only when `productionEnabled()`
- US + CA production countries; Sweden validation-only
- Merchant billing notice

### Batch 2 — Registration state machine
- `credentials_issued` → `child_oauth_verifying` → `registered` | `child_oauth_failed`
- Success page blocked without connected child OAuth
- Merchant-safe `FedExConnectionFailurePresenter`

### Batch 3 — Security + idempotency
- Durable Transaction A / OAuth outside TX / Transaction B
- Unique `registration_session_id`, unique `fedex_active_store_key`
- Encrypted Model A account numbers + last4
- `clearTransientFedExSecrets()` after finalization
- Dedicated SQLite USPS partial-index repair migration (`030000`) — not embedded in FedEx migrations

### Batch 4 — Lifecycle (final lifecycle-safety pass)
- Canonical replacement owned by `FedExIntegratorRegistrationOrchestrator`
- Lock order: session → read IDs → sort → lock both accounts → validate → replace
- Incoming invariants checked **before** outgoing retirement
- Activation / unique-key failure rolls back the entire replacement TX; outgoing preserved; incoming failed in a separate TX
- Resume: provider **and** connection_model required; OAuth-only; reconnect resume preserves outgoing until activation
- Verify: exact active key; never assigns key; transient vs invalid credential handling
- Disconnect: idempotent; secrets cleared; token cache forgotten **after** commit
- `replaceActiveAccount()` throws `LogicException` (no silent no-op)
- Model relationships: `replacedByCarrierAccount` / `replacedCarrierAccounts`

### Batch 5 — Route isolation + throttles
- `routes/fedex.php`, `routes/usps.php`, thin `routes/carriers.php`
- Validation / Model B gates via `FedExConfig`
- Model A card = Manage only; shared disable/destroy → 422

### Batch 6–7 — Tests + preflight
- Lifecycle safety suite + actual lifecycle migration up/down
- `php artisan fedex:production-preflight` (no secret echo)

## Explicitly deferred (6C-5B–5E)

| Slice | Topic |
|-------|--------|
| 6C-5B | Production checkout rates |
| 6C-5C | Order label purchase |
| 6C-5D | Tracking sync + cancel |
| 6C-5E | Merchant rates/labels/track UI beyond connection manage |
