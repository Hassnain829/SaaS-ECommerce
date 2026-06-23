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

## ⚠️ CURRENT PHASE (CRITICAL)

---

### 🔥 Day 14 — Product Workspace

Goal:
- full product detail page
- all data visible
- imported data visible

Status:
- implemented
- UX still needs improvement

---

### 🔥 Day 15 — Variant System

Goal:
- option groups (size, color)
- combinations
- variant-level stock/price
- variant images
- easy merchant UX

Status:
- partially complete
- needs usability improvement

---

### 🔥 Day 16 — Variant Import

Goal:
- import multi-variant products
- map option groups
- create combinations automatically

---

### 🔥 Day 17 — Custom Fields

Goal:
- make custom_fields usable
- readable labels
- better grouping

---

### 🔥 Day 18 — Product UX Completion

Goal:
- clean product list
- filters
- better navigation
- bulk actions UX

---

# 🛒 NEXT PHASE — COMMERCE

---

## Day 19–24

- customers
- addresses
- orders
- order items
- order detail
- inventory deduction

---

# 🚚 FULFILLMENT

---

## Day 25–29

- shipping carriers (DHL, UPS)
- shipment system
- async shipping jobs

---

# 💳 BILLING

---

## Day 30–34

- subscription plans
- invoices
- payment handling

---

# 🔗 INTEGRATIONS & SECURITY

---

## Day 35–40

- API keys
- webhooks
- notifications
- logs
- security

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

# 🧹 Repository hygiene (CLEAN-1 / CLEAN-1A)

CLEAN-1 adds safe source/archive/cleanup tooling only. **CLEAN-1A** fixes archive template export, Laravel placeholder preservation, tracked-file deletion protection, and path traversal safety. It does **not** change admin panel, merchant UI, or carrier business logic.

Commands:

- `php artisan project:hygiene-report` — read-only size/leak report
- `php artisan project:cleanup` — dry-run runtime cleanup (use `--force` to delete; never deletes Git-tracked files)
- `php artisan project:source-archive` — export-safe source ZIP (**Git required**)

Docs: `docs/cleanup/PROJECT_CLEANUP_MASTER_PLAN.md`

CLEAN-1 is accepted only after CLEAN-1A passes. Future phases: CLEAN-2 (file organization), CLEAN-3 (scheduled pruning), CLEAN-4 (refactoring).

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