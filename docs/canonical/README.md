# Canonical Documentation Index

These root files, plus `docs/current/PROJECT_STATE.md`, are the **source of truth** for product direction, architecture, and build order. When any other doc conflicts, follow the authority order below.

## Authority order

1. Current source code, migrations, routes, configuration, and tests
2. [`../current/PROJECT_STATE.md`](../current/PROJECT_STATE.md) — volatile current state
3. [`../handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`](../handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md) — release-readiness scope
4. [`../fedex/MODEL_A_INTEGRATOR_PROVIDER.md`](../fedex/MODEL_A_INTEGRATOR_PROVIDER.md) and [`../architecture/CARRIER_CODE_STRUCTURE.md`](../architecture/CARRIER_CODE_STRUCTURE.md)
5. Root product/roadmap/structure documents
6. Active implementation plans
7. [`../archive/`](../archive/) — historical evidence only

| Document | Purpose |
|----------|---------|
| [`../../ENTERPRISE_PROJECT_CONTEXT.md`](../../ENTERPRISE_PROJECT_CONTEXT.md) | Product vision, architecture rules, merchant UX principles |
| [`../../ENTERPRISE_ROADMAP_2026.md`](../../ENTERPRISE_ROADMAP_2026.md) | Phased build plan and acceptance criteria |
| [`../../PROJECT_BRAIN.md`](../../PROJECT_BRAIN.md) | Condensed project memory for agents |
| [`../../AGENTS.md`](../../AGENTS.md) | Agent/developer operating instructions |
| [`../../PROJECT_STRUCTURE.md`](../../PROJECT_STRUCTURE.md) | Codebase map — folders, controllers, services, docs layout |

## Active DR-05 / DR-06 plans

1. [`../plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md`](../plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md) — Batches 1–6 correction architecture; ten-scenario browser gate closed 2026-08-20
2. [`../plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md`](../plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md) — merchant go-live checklist; implemented
3. [`../handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`](../handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md) — Batch 8 acceptance mapping
4. [`../WORDPRESS_SAAS_COMMERCE_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md`](../WORDPRESS_SAAS_COMMERCE_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md) — locked WordPress/SaaS architecture
5. DR-06 owner/manager/staff acceptance across two stores — complete (automated)
6. Continue remaining readiness P0 (DR-07+)

The architecture plan above is the locked product architecture. Volatile status lives in `docs/current/PROJECT_STATE.md`.

Clarify:

* current operational state in `docs/current/PROJECT_STATE.md` beats historical roadmap text
* code and migrations remain implementation truth
* archived documents are evidence only

Active Cursor rules: [`.cursor/rules/`](../../.cursor/rules/)

Volatile status always points to [`docs/current/PROJECT_STATE.md`](../current/PROJECT_STATE.md).
