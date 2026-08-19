# Documentation Index

Runtime application code lives in `app/`, `routes/`, `resources/`, and `database/`. This folder holds **current state, readiness scope, architecture, operations, and plans**. Historical completion reports live under `archive/` and are **not** authoritative.

## Start here (active order)

1. [`current/PROJECT_STATE.md`](current/PROJECT_STATE.md) — volatile current project state
2. [`handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`](handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md) — release-readiness P0
3. [`canonical/README.md`](canonical/README.md) — root enterprise docs index
4. [`plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md`](plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md) — DR-05 correction architecture and browser gate
5. [`plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md`](plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md) — Batch 7 plan (blocked; runtime not started)
6. [`architecture/`](architecture/) — carrier structure and refactoring boundaries
7. [`operations/`](operations/) — security, release, retention

Also useful:

- [`fedex/MODEL_A_INTEGRATOR_PROVIDER.md`](fedex/MODEL_A_INTEGRATOR_PROVIDER.md) — FedEx Model A architecture
- [`cleanup/CLEANUP_DECISION_LOG.md`](cleanup/CLEANUP_DECISION_LOG.md) — hygiene decisions
- [`LOCAL_SETUP.md`](LOCAL_SETUP.md) — local environment setup

## Folder guide

| Folder | What it contains |
|--------|------------------|
| `current/` | Authoritative volatile project state |
| `handoffs/` | Active readiness / handoff documents |
| `canonical/` | Pointers to root enterprise docs |
| `architecture/` | Structural docs for controllers, carriers, refactoring |
| `cleanup/` | Active cleanup decision log + source archive guide |
| `fedex/` | Active FedEx Model A documentation |
| `operations/` | Security hardening, release checklist, retention |
| `plans/` | Active approved implementation plans; status must be checked against current state |
| `archive/` | Historical reports only — **non-authoritative**; excluded from Cursor and source exports |

Do not treat `docs/archive/**` as current instructions. Prefer source code and `docs/current/PROJECT_STATE.md` on conflict.

DR-05 order: correction contract and ten-scenario evidence → Batch 7 merchant cutover plan/implementation → Batch 8 acceptance → DR-05 sign-off → DR-06 merchant acceptance. Phase 9 is a separate, unimplemented workstream.
