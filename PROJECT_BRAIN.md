# 🧠 PROJECT BRAIN — BaaS Core (Full System Brain)

---

## 🧭 What we are building

We are building an **enterprise-ready multi-tenant SaaS ecommerce platform**

Target:
- Shopify alternative
- WooCommerce alternative
- Amazon Seller-style system

BUT:

👉 simpler  
👉 more intuitive  
👉 less frustrating  
👉 more scalable  

---

## 🎯 Core Vision

Most platforms fail because:
- too complex
- too many steps
- unclear data
- weak variant handling
- poor import systems

Our platform must:

- reduce merchant confusion
- reduce clicks
- reduce thinking effort
- increase visibility
- increase control

---

## 🧠 Golden Rule

👉 If the merchant feels confused → system is wrong  
👉 If developer uses shortcut → system will break later  

---

# 🏗️ SYSTEM ARCHITECTURE PRINCIPLES

---

## 1. Multi-store SaaS (STRICT)

- every entity belongs to a store
- no cross-store access
- use current.store everywhere

---

## 2. Hybrid Data Model

| Type | Storage |
|------|--------|
| Core data | tables |
| Flexible fields | meta.custom_fields |
| Extra import data | meta.import_extra |

---

## 3. Import → Product → UX pipeline

Import → Normalize → Store → Expose → Manage

👉 data must NOT just be stored  
👉 it must be usable in UI

---

## 4. Workspace-first UX

Core entities must have real workspaces:

- Product workspace
- Order workspace (future)
- Customer workspace (future)

NOT:
- modal-only
- list-based editing

---

## 5. No Hacks Rule

NEVER:
- modal/list bridge flows
- redirect loops
- temporary fixes

ALWAYS:
- direct flows
- clean routes
- stable UX

---

# 🚀 DEVELOPMENT ROADMAP (FULL)

---

## ✅ Sprint 1 — SaaS Foundation

- store system
- roles & permissions
- current.store middleware

---

## ✅ Sprint 2 — Catalog Core

- products
- brands
- tags
- categories
- product_images
- product_categories pivot
- stock movements

---

## ✅ Day 12 — Bulk Import

- CSV/XLSX parsing
- mapping system
- validation
- queue processing

---

## ✅ Day 13 — Import Enhancements

- progress tracking
- error handling
- retry system
- row-level debugging

---

## ✅ CURRENT STATE (2026-06-24)

Repository cleanup **CLEAN-1 through CLEAN-4 is complete.** FedEx Model A integrator connectivity and validation tooling are implemented; **production carrier approvals and live carrier operation remain pending.**

**Next focus:** Phase **5R-1** tax foundation (planned) — carrier-neutral portal completion while courier production approvals remain pending.

### Phase 5R (calculation and tax)

- **5R-0 (completed 2026-06-24):** Current calculation audit — `docs/audit/PHASE_5R_0_CURRENT_CALCULATION_AUDIT.md`
- **5R-1 (in progress — Slices 1A–1B complete):** Tax schema, settings UI, bootstrap, versioning — `docs/implementation/PHASE_5R_1_SLICE_1A_TAX_SCHEMA_REPORT.md`, `docs/implementation/PHASE_5R_1_SLICE_1B_TAX_SETTINGS_UI_REPORT.md`; tax calculation pending (Slice 2+)
- **5R-2 (pending):** Coupons
- **5R-3 (pending):** Checkout/order totals hardening

### Implemented foundations

- Sprint 1–2 catalog and store SaaS foundation
- Day 12–18 catalog import, product workspace, variants, variant import, custom fields, product UX polish
- Commerce core: customers, orders, order items, addresses
- Multi-location inventory and inventory reservations
- Manual fulfillment and shipments
- Checkout delivery methods
- Stripe platform checkout and Stripe Connect foundation
- External checkout synchronization (developer storefront prototype)
- Security logs and user sessions
- FedEx Model A connectivity and validation workspace
- USPS public API foundation
- CLEAN-1 through CLEAN-4 repository hygiene and controlled refactoring

### Partial or pending

- Tax and coupons (**5R-0 audit complete; 5R-1 tax foundation planned, not implemented**)
- Refunds, returns, and exchanges
- Shipping rules and async carrier production jobs
- Production carrier approvals and live carrier rates/labels/tracking
- API keys/scopes, webhooks, event outbox
- Notifications
- SaaS billing and subscriptions
- Markets and B2B
- Observability
- Platform admin operations

---

# 💡 CORE SYSTEM RULES

---

## Product System

Must support:
- simple products
- variant products
- service products
- digital products

---

## Variant System

Must:
- be easy to understand
- show option groups clearly
- show combinations clearly
- avoid duplicates
- link images correctly

---

## Import System

Must:
- handle messy data
- preserve unknown columns
- allow custom mapping
- scale to 10k+ products

---

## UX System

Must:
- avoid confusion
- avoid technical wording
- guide the user
- show progress clearly

---

# 🚫 WHAT WE AVOID

---

## UX mistakes

- too many buttons
- confusing flows
- hidden data
- technical language

---

## Architecture mistakes

- cross-store leaks
- DB misuse
- storing images in DB
- mixing structured + unstructured data

---

## Product mistakes

- incomplete features
- partial flows
- “it works technically” but bad UX

---

# 🧠 DECISION FRAMEWORK

---

Before implementing anything:

Ask:

1. Is this easier for merchant?
2. Is this scalable?
3. Is this consistent?
4. Is this future-proof?
5. Is this clean?

If NO → rethink

---

# 🧹 Repository hygiene (CLEAN-1 through CLEAN-4)

CLEAN-1/1A add safe archive/cleanup tooling. **CLEAN-2** reorganizes carrier code. **CLEAN-3** adds age-based runtime retention. **CLEAN-3A** enforces marked sandboxes for destructive tests and blocks `--force` against the real worktree in testing. **CLEAN-4** extracts four internal boundaries (FedEx test presenter, registration payload builder, import row mapper, onboarding routes) with characterization tests and no behavior change.

Carrier layout reference: `docs/architecture/CARRIER_CODE_STRUCTURE.md`
Refactoring boundaries: `docs/architecture/REFACTORING_BOUNDARIES.md`
Retention reference: `docs/operations/RUNTIME_STORAGE_RETENTION.md`

Commands:

- `php artisan project:hygiene-report`
- `php artisan project:cleanup` — dry-run runtime cleanup (never deletes Git-tracked files)
- `php artisan project:source-archive` — export-safe source ZIP (**Git required**, uses worktree `.gitattributes`)
- `php artisan project:retention` — conservative age-based retention (dry-run default; `--force` requires `PROJECT_RETENTION_ENABLED=true`)

Docs: `docs/cleanup/PROJECT_CLEANUP_MASTER_PLAN.md`, `docs/cleanup/CLEAN_4_CONTROLLED_REFACTORING_REPORT.md`

Further large-file decomposition: `docs/REFACTORING_ROADMAP.md` (remaining deferred targets).

---

# 🚚 Carrier connectivity architecture (locked)

**Model A / Official Integrator Provider** is primary for FedEx and future couriers where supported. Platform owns integrator credentials; merchants connect merchant-owned courier accounts through onboarding.

**Model B / merchant developer credentials** is temporary developer fallback only (`FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED`). Do not treat Model B as the launch architecture.

FedEx Model A is implemented (Phase 6C-4); validation submission and production enablement remain in progress.

---

# 🔥 FINAL GOAL

---

Build a platform where:

- merchants WANT to switch
- managing products is EASY
- variants are SIMPLE
- imports are TRUSTED
- workflows are CLEAR
- system is SCALABLE

---

## ⚡ FINAL LINE

👉 If merchant struggles → we failed  
👉 If system is hacky → it will collapse later