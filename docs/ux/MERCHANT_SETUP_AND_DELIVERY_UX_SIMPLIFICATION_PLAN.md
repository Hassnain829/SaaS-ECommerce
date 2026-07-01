# Merchant Setup & Delivery UX Simplification Plan

**Project:** E_COMMERCE_OFFICE (SaaS-Static-Blade)  
**Date:** 2026-05-24  
**Status:** Analysis & redesign planning only — **no implementation in this document**  
**Author:** Cursor UX audit (Guidance.txt + observation.txt)

---

## 1. Executive diagnosis

### What is wrong (not backend weakness)

The platform’s **backend is ahead of the merchant UI**. Shipping zones, delivery methods, fulfillment routing, carrier accounts, and tax calculation work in code and tests. The merchant experience fails because the UI **mirrors the data model** instead of answering merchant business questions.

> **Merchant question:** “Main kahan se ship karta hoon, kahan deliver karta hoon, customer ko kya option milega, kitna charge hoga?”  
> **Current UI answer:** Five tabs, comma-separated text fields, duplicate destination concepts, and developer terminology.

This violates locked product principles (`ENTERPRISE_PROJECT_CONTEXT.md`, `.cursor/rules/01-product-vision-and-success-criteria.mdc`):

- Features are not complete when Blade exists but workflow is confusing.
- Merchant effort must decrease, not increase.
- Technical jargon must not appear in default surfaces.

### Root cause: information architecture, not feature count

| Symptom | Cause |
|--------|--------|
| TX vs Texas confusion | Raw text fields with no normalization UI; partial backend normalization only |
| Same data in Location + Zone | Two different backend purposes exposed with identical field labels |
| Shipping $0 at checkout | Silent eligibility failures (max order, region mismatch) with no merchant-facing explanation |
| Developer finds setup hard | No guided journey; equal-weight tabs (Overview, Carriers, Zones, Methods, Locations) |
| “Enterprise” feels harder than Shopify | Enterprise = **power with progressive disclosure**, not **all fields visible at once** |

### Correct direction (locked)

**Do not delete, merge, or rewrite backend domain logic.**

Enterprise UX means:

- Same capabilities
- Organized into a **simple default journey**
- Advanced routing/carrier/tax controls in **collapsed Advanced settings**
- Structured inputs (selectors, chips) writing to **existing models**

Simplicity comes from **orchestration and progressive disclosure**, not loss of domain integrity.

---

## 2. Page-level UX audit

Evidence from production Blade templates and merchant screenshots (Demo Digital store).

### 2.1 Locations (`settings.locations.index` → `user_view/locations.blade.php`)

| Field / control | Why confusing | Duplicates | Merchant risk | Recommended replacement | Backend unchanged |
|-----------------|---------------|------------|---------------|-------------------------|-------------------|
| Service countries (raw `US, CA`) | Looks like “where I deliver” | **Yes** — mirrors zone countries | Merchant fills zone + location differently; routing breaks | Hide in Simple mode; Advanced: “Countries this location can fulfill from” multi-select | `locations.service_countries[]` |
| Service regions (raw `CA, TX`) | Abbrev vs full name unclear | **Yes** — mirrors zone regions | TX/Texas mismatch blocks fulfillment routing | Advanced only; state multi-select (reuse `TaxCountryCatalog` pattern) | `locations.service_regions[]` |
| Service postal patterns | Undocumented `606*` format | **Yes** — mirrors zone postal | Typo silently excludes origin | Advanced only; chip/token input | `locations.service_postal_patterns[]` |
| Routing priority | Ops jargon | No | Wrong priority = unexpected origin | Advanced only; label “Fulfillment priority” | `locations.routing_priority` |
| Fulfill online orders | OK concept, buried in long form | No | Unchecked = no ship-from | Simple: default ON, visible toggle | `locations.fulfills_online_orders` |
| Offer pickup | OK but mixed with routing | No | Enables pickup methods later | Simple: visible when pickup method exists | `locations.pickup_enabled` |
| Country code (2-letter) on address | Merchant expects country name | No | Invalid codes | Searchable country select → ISO-2 | `locations.country_code` |
| State/Province free text | Inconsistent with zone regions | Partial | Format drift | State select when country has catalog | `locations.state` |
| Ship-from readiness badge | Good signal, no fix action inline | No | Carrier testing blocked | Link to “Complete address” inline | `CarrierOriginReadinessService` |
| Routing column in table | Dense dump of all advanced fields | No | Overwhelming scan | Summary line + “Advanced routing” expand | display only |
| Page also duplicated under Shipping → Locations tab | Two entry points, same complexity | **Yes** | “Which page is canonical?” | Single Simple entry; tab becomes Advanced shortcut | routes preserved |

### 2.2 Shipping zones (`shippingAutomation?tab=zones` → `drawers.blade.php`)

| Field / control | Why confusing | Duplicates | Merchant risk | Recommended replacement | Backend unchanged |
|-----------------|---------------|------------|---------------|-------------------------|-------------------|
| Label “Zone name” | Developer term | No | — | **Delivery area name** | `shipping_zones.name` |
| Countries (`US, CA` placeholder) | Raw comma string | **Yes** — location service countries | CA = Canada vs California ambiguity | Searchable multi-select; store ISO-2 | `shipping_zones.countries[]` |
| Regions (`California, Ontario`) | No TX/Texas guidance | **Yes** | Silent non-match at checkout | State/province multi-select after country | `shipping_zones.regions[]` |
| Postal patterns (`941*, 10001`) | Hidden wildcard rules | **Yes** | Wrong zip = no delivery options | Chip input + examples | `shipping_zones.postal_patterns[]` |
| Sort order | Developer term | No | Rarely needed | Advanced: “Display order” | `shipping_zones.sort_order` |
| Active checkbox | OK | No | Inactive zone = empty checkout | Simple: “Available to customers” | `shipping_zones.is_active` |
| Coverage column in table | Long comma string | No | Unreadable | Human summary: “United States · Texas, California · 4 ZIP rules” | display only |

### 2.3 Delivery methods (`shippingAutomation?tab=methods` → `drawers.blade.php`)

| Field / control | Why confusing | Duplicates | Merchant risk | Recommended replacement | Backend unchanged |
|-----------------|---------------|------------|---------------|-------------------------|-------------------|
| Method name vs Customer label | Two names, unclear difference | No | Redundant or empty labels | Simple: one “Customer-facing name” + optional speed subtitle | `name`, `delivery_speed_label` |
| Zone dropdown | Requires understanding zones first | No | Method without zone = broken | Guided flow creates area first; show area name read-only in Simple | `shipping_methods.shipping_zone_id` |
| Carrier account | Shown before merchant needs it | No | Blocks simple flat-rate setup | Advanced; Simple defaults to manual delivery | `shipping_methods.carrier_account_id` |
| Rate type (4 options at once) | All pricing fields visible | No | Wrong type selected | Conditional: Fixed / Free / Free over amount | `shipping_methods.rate_type` |
| Flat rate + Free over + Min/max order | All visible simultaneously | No | **Max order $10 blocked $50 cart** (observed) | Simple: price OR free-over only; min/max Advanced | `flat_rate`, `free_over_amount`, `min_order_amount`, `max_order_amount` |
| Checkout vs Active toggles | Two similar flags | No | Method not at checkout | Simple: single “Show at checkout” (maps to both) | `enabled_for_checkout`, `is_active` |
| Min/max days | OK for merchants | No | — | Simple: “Delivery estimate” single range | `estimated_min_days`, `estimated_max_days` |
| Sort order | Developer term | No | — | Advanced | `shipping_methods.sort_order` |
| Drawer sections (5 blocks) | Cognitive overload | No | Abandon setup | Simple: 4 questions; rest Advanced | — |

### 2.4 Carriers (`shippingAutomation?tab=carriers`)

| Field / control | Why confusing | Duplicates | Merchant risk | Recommended replacement | Backend unchanged |
|-----------------|---------------|------------|---------------|-------------------------|-------------------|
| FedEx integrator + USPS + manual mixed | High enterprise surface on same page | No | Small store overwhelmed | Simple: “Manual delivery (default)”; Carriers in Advanced / Connect flow | `carrier_accounts` * |
| Connection status badges | Technical states | No | — | Plain language + next step CTA | existing enums |
| Origin for carrier testing | Tied to Locations concept | Partial | Wrong origin | Guided Step 1 ship-from | `default_origin_location_id` |

### 2.5 Taxes (`settings.taxes.index` → `settings/taxes.blade.php`)

| Field / control | Why confusing | Duplicates | Merchant risk | Recommended replacement | Backend unchanged |
|-----------------|---------------|------------|---------------|-------------------------|-------------------|
| Five status cards + behavior section | Better than shipping, still dense | No | — | Preset cards: Off / Add at checkout / Included in price | `tax_settings.*` |
| Four separate checkboxes | No preset grouping | No | Wrong combination | Map presets → existing booleans | `enabled`, `prices_include_tax`, etc. |
| Calculation address (read-only) | Looks editable | No | — | Helper text only; hide label in Simple | `calculation_address` |
| Tax rate form | **Good pattern** — country select + region select | No | — | **Reuse this pattern for delivery areas** | `tax_rates.*` |
| Priority in advanced | OK | No | — | Keep collapsed | `tax_rates.priority` |

**Note:** Tax UX is **closer to target** than Shipping/Locations. `TaxCountryCatalog` + `tax_rate_form_fields.blade.php` is the reference implementation for structured geography inputs.

### 2.6 Navigation & setup visibility

| Area | Problem | Recommendation |
|------|---------|----------------|
| Settings sidebar | 7 equal items under Settings; Locations separate from Shipping | Group into **Delivery** hub + **Checkout & tax** |
| `generalSettings` | “Configure shipping & delivery” dumps to 5-tab page | Link to **Delivery setup** overview with health card |
| Onboarding (3 steps) | Products only; no delivery/tax/payment setup | Post-onboarding checklist links to Delivery setup (not in onboarding rewrite yet) |
| Shipping overview checklist | Exists but uses backend terms (“delivery zone”, “fulfillment origin”) | Rename + plain-language summary + single CTA |
| Dev test storefront | Developer simulator, not merchant UI | Out of scope for merchant redesign; keep separate |

### 2.7 Developer test storefront (reference only)

Observed: platform checkout skips delivery when `delivery_options: []` — merchant config issue (max order, regions) compounded by **no dashboard warning** that methods exist but none match. Merchant dashboard should surface **“No customers can receive delivery at checkout”** diagnostic when methods/zones misconfigured.

---

## 3. Duplicate-field map

```
MERCHANT-VISIBLE DUPLICATION
══════════════════════════════════════════════════════════════════

┌─────────────────────────────┐         ┌─────────────────────────────┐
│ LOCATION (ship-from)        │         │ SHIPPING ZONE (delivery)    │
│ service_countries[]         │  ≈ UI   │ countries[]                 │
│ service_regions[]           │  ≈ UI   │ regions[]                   │
│ service_postal_patterns[]   │  ≈ UI   │ postal_patterns[]           │
└─────────────────────────────┘         └─────────────────────────────┘
         │                                         │
         │ PURPOSE (backend — keep both)           │
         ▼                                         ▼
 FulfillmentOriginRouter                   ShippingZoneMatcher
 LocationServiceAreaMatcher                DeliveryOptionService
 "Which origin CAN fulfill                   "Which customers MAY
  this destination?"                         receive delivery?"
```

### Locked merchant rule (UI copy)

| Concept | Decides | Simple mode |
|---------|---------|-------------|
| **Delivery area** (`ShippingZone`) | Customer checkout eligibility | **Always configure** |
| **Location service area** (`Location.*service_*`) | Which ship-from location handles an order | **Hidden** — default: main location serves all delivery areas |
| **Delivery option** (`ShippingMethod`) | Price/speed shown at checkout | **Always configure** |
| **Carrier account** | Label/rates provider | **Optional** — manual default |

### Safe defaults (no migration)

1. New locations: `service_countries`, `service_regions`, `service_postal_patterns` = **empty** → matcher treats as “serve store default / all destinations” per `LocationServiceAreaMatcher`.
2. Simple setup never writes location service fields unless Advanced routing opened.
3. Delivery area remains **source of truth** for checkout `delivery-options` API.

### Known backend inconsistency (document for Batch 2)

- `ShippingZoneMatcher` now maps US state abbreviations ↔ full names (recent fix).
- `LocationServiceAreaMatcher` still uses exact region string match — Advanced routing UI should normalize on save, not change matcher logic in Batch 1.

---

## 4. Locked merchant-facing definitions

| Internal model | Merchant term | Merchant question |
|----------------|---------------|-------------------|
| `Location` | **Ship-from location** | Where do you keep and ship products from? |
| `ShippingZone` | **Delivery area** | Where do you deliver? |
| `ShippingMethod` | **Delivery option** | How can customers receive their order? |
| `CarrierAccount` | **Delivery provider** | Who delivers the package? (optional) |
| `TaxSetting` + `TaxRate` | **Checkout tax** | Should tax be added, and where? |

**Do not merge models.** UI terminology only.

---

## 5. Proposed information architecture

### Settings navigation (target)

```
SETTINGS
├── Store details          (generalSettings — profile, currency, timezone)
├── Payments               (settings.payments.*)
├── Checkout & tax         (settings.taxes.index + checkout mode summary)
├── Delivery               (NEW hub — default landing)
│   ├── Setup overview     (guided status + summary)
│   ├── [Advanced]
│   │   ├── Ship-from locations
│   │   ├── Delivery areas
│   │   ├── Delivery options
│   │   ├── Carriers
│   │   └── Routing rules
├── Test storefront
└── Team & security
```

### Route compatibility (no breaking changes)

| Current route | Future role |
|---------------|-------------|
| `shippingAutomation` | Redirect or render **Delivery setup overview** (same URL OK) |
| `settings.locations.index` | Advanced → Ship-from locations |
| `settings.taxes.index` | Checkout & tax (unchanged URL, simplified Simple section) |
| `settings.shipping.zones.*` | Used by guided Step 2 controller/service |
| `settings.shipping.methods.*` | Used by guided Step 3 |
| `shipping.carriers.connect.*` | Advanced → Carriers |

**Locations removed from top-level sidebar in Simple mode** — accessed via Delivery setup Step 1 or Advanced.

---

## 6. Guided setup flow: “Delivery setup”

### Step 1 — Ship from

**Question:** Where do you ship from?

Visible:
- Location name
- Address (line, city, state select, postal, country select)
- “Fulfill online orders” (default ON)

Hidden (Advanced):
- Routing priority
- Service countries/regions/postal patterns
- Pickup (until pickup delivery option added)

Writes to: `Location` via existing `LocationController` or new `DeliverySetupController` orchestrator.

### Step 2 — Deliver to

**Question:** Where do you deliver?

Visible:
- Country multi-select
- Coverage mode: Entire country | Selected states | Selected ZIP/postal codes
- Structured region + postal inputs

Hidden (Advanced):
- Multiple delivery areas
- Sort order
- Overlapping zones

Writes to: `ShippingZone` via existing zone store/update endpoints.

### Step 3 — Customer delivery option

**Question:** What should customers see at checkout?

Visible:
- Customer-facing name (e.g. “Standard delivery”)
- Delivery speed label (e.g. “2–4 business days”)
- Price type: Fixed price | Free | Free over order amount
- Price / threshold (conditional)
- Delivery estimate (days)

Hidden (Advanced):
- Carrier account
- Min/max order eligibility
- Separate checkout/active toggles → unified “Available at checkout”
- Sort order
- Description

Writes to: `ShippingMethod` + auto-link manual carrier if none selected.

### Step 4 — Tax

**Question:** How should tax work?

Presets (map to existing booleans):
1. **Do not add tax** → `enabled=false`
2. **Add tax at checkout** → `enabled=true`, `prices_include_tax=false`
3. **Prices already include tax** → `enabled=true`, `prices_include_tax=true`

Then:
- Shipping taxable toggle
- Default product taxable toggle
- Add country/state rates (existing rate UI)

Writes to: `TaxSetting`, `TaxRate` — **no formula changes**.

### Step 5 — Review and activate

Plain-language summary example:

> Orders ship from **Main location** (New York, NY).  
> You deliver to **United States — Texas, California**.  
> Customers see **Standard delivery** for **$5.00** (free over **$50.00**).  
> **Texas** checkout tax is **10%**.

Each line: **Edit** → deep-link to step.

---

## 7. Simple mode vs Advanced mode

### Simple mode (default)

| Area | Visible fields |
|------|----------------|
| Ship-from | Name, address, fulfill online |
| Delivery area | Countries, states OR whole country, optional ZIP chips |
| Delivery option | Name, speed, price type, price/threshold, estimate |
| Tax | Preset + rates |
| Carriers | “Manual delivery” implied |

### Advanced mode (collapsed `<details>` or “Advanced settings” panel)

| Area | Visible fields |
|------|----------------|
| Locations | Multiple locations, routing priority, service areas, pickup |
| Delivery areas | Multiple zones, sort order, overlapping rules, prefix patterns |
| Delivery options | Carrier mapping, min/max order(--) order, sort order, rate types including carrier_calculated_later |
| Carriers | FedEx/USPS integrator, credentials, validation workspace |
| Routing | Location service area constraints vs delivery area |

**Rule:** Advanced must not be required for a working single-location US store with flat-rate shipping.

---

## 8. Structured input design

Reuse **`TaxCountryCatalog`** and patterns from `tax_rate_form_fields.blade.php`.

### Countries
- Searchable multi-select
- Display: “United States”
- Store: `US`
- Component: `resources/views/components/geo/country-multi-select.blade.php` (proposed)

### Regions
- After country selection: checkbox/multi-select of states/provinces
- Display: “Texas”
- Store: `TX` (normalized)
- Never ask merchant to type `TX` vs `Texas`

### Postal codes
- Chip/token input component
- Enter/comma adds chip
- Validate format per country
- Distinguish exact (`75002`) vs prefix (`606*`) with chip badge
- Normalize uppercase, dedupe

### Delivery price
- Radio: Fixed | Free | Free over amount
- Show only relevant numeric field(s)
- Map to `rate_type`: `flat`, `free`, `flat` + `free_over_amount`

### Controller boundary
- New `DeliveryAreaInputNormalizer` (proposed) converts UI arrays → existing `listFromInput` format
- **No schema change**

---

## 9. Backend → proposed UI mapping

| Backend field | Simple UI control | Advanced UI |
|---------------|-------------------|-------------|
| `locations.name` | Text | Text |
| `locations.address_*` | Address form | Address form |
| `locations.country_code` | Country select | Country select |
| `locations.state` | Region select | Region select |
| `locations.fulfills_online_orders` | Checkbox (default on) | Checkbox |
| `locations.service_*` | — | Multi-select / chips |
| `locations.routing_priority` | — | Number |
| `shipping_zones.name` | Auto-generated or simple label | Editable |
| `shipping_zones.countries` | Country multi-select | Same |
| `shipping_zones.regions` | State multi-select | Same |
| `shipping_zones.postal_patterns` | ZIP chips (optional) | ZIP chips |
| `shipping_zones.sort_order` | — | Number |
| `shipping_methods.name` | Customer-facing name | + internal name |
| `shipping_methods.delivery_speed_label` | Speed text | Speed text |
| `shipping_methods.rate_type` | Price type radio | Full select |
| `shipping_methods.flat_rate` | Price input | Price input |
| `shipping_methods.free_over_amount` | Threshold input | Threshold input |
| `shipping_methods.min/max_order_amount` | — | Optional limits with helper |
| `shipping_methods.shipping_zone_id` | Auto from Step 2 | Dropdown |
| `shipping_methods.carrier_account_id` | Auto manual | Select |
| `shipping_methods.enabled_for_checkout` | Single “At checkout” | Separate toggles |
| `tax_settings.*` | Preset cards | Individual toggles |

---

## 10. Route / controller / service impact map

### New (proposed — Batch 1+)

| Artifact | Purpose |
|----------|---------|
| `DeliverySetupController` | Guided steps, read models, compose summaries |
| `DeliverySetupStatusService` | Health: ship-from / area / option / tax / payment |
| `DeliveryAreaInputNormalizer` | UI arrays → existing list fields |
| `resources/views/user_view/delivery/setup.blade.php` | Overview + step wizard shell |
| `resources/js/delivery-setup.js` | Step navigation, chip inputs (optional Vite module) |

### Existing — read-only orchestration, no logic rewrite

| Artifact | Change type |
|----------|-------------|
| `ShippingSettingsController` | Batch 2: accept normalized arrays; keep `listFromInput` |
| `LocationController` | Batch 2: hide service fields from Simple POST |
| `TaxSettingsController` | Batch 1: preset mapping only |
| `ShippingZoneMatcher` | **No change** (already handles US aliases) |
| `DeliveryOptionService` | **No change** |
| `CheckoutShippingService` | **No change** |
| `FulfillmentOriginRouter` | **No change** |
| `LocationServiceAreaMatcher` | Batch 2 optional: shared region normalizer |
| `TaxCalculator` | **No change** |

---

## 11. Blade / JS files likely affected

### Batch 1 (terminology + overview)
- `resources/views/layouts/user/user-sidebar.blade.php`
- `resources/views/user_view/shippingAutomation.blade.php`
- `resources/views/user_view/shipping/tabs/overview.blade.php`
- `resources/views/user_view/generalSettings.blade.php`
- `resources/views/user_view/locations.blade.php` (copy only)

### Batch 2 (structured inputs + simple editors)
- `resources/views/user_view/shipping/partials/drawers.blade.php`
- `resources/views/user_view/shipping/tabs/zones.blade.php`
- `resources/views/user_view/shipping/tabs/methods.blade.php`
- `resources/views/user_view/locations.blade.php`
- New: `resources/views/components/geo/*`
- New: `resources/views/user_view/delivery/*`
- `app/Http/Controllers/ShippingSettingsController.php`
- `app/Http/Controllers/LocationController.php`

### Batch 3 (polish)
- `resources/views/user_view/shipping/tabs/carriers.blade.php`
- `resources/views/user_view/settings/taxes.blade.php`
- `resources/views/user_view/shipping/tabs/locations.blade.php`
- Mobile drawer accessibility pass on all shipping drawers

### Explicitly out of scope
- FedEx validation workspace blades
- `dev-test-storefront/*` (separate developer tool; minor copy OK)
- Coupon / Phase 5R-2

---

## 12. Backward-compatibility plan

1. **URLs:** Keep `shippingAutomation`, `settings.locations.index`, `settings.taxes.index` working.
2. **API:** No checkout API changes.
3. **Data:** Existing comma-separated values remain valid; editors parse and display as chips/selects.
4. **Advanced users:** Full tabbed UI remains under “Advanced” until feature parity confirmed.
5. **Permissions:** Same `settings.view` / `settings.manage` middleware.
6. **Snapshots:** Order/checkout snapshots unchanged — still store method/zone snapshots at selection time.

**Migrations required:** **None** for Batch 1–3 as described.

---

## 13. Test strategy

| Layer | Tests |
|-------|-------|
| Unit | Input normalizer: countries/regions/postal chips → array storage |
| Unit | `DeliverySetupStatusService` health states |
| Feature | Guided setup POST creates Location + Zone + Method (store-scoped) |
| Feature | Simple mode POST does not require service_area fields |
| Regression | Existing `Phase6CheckoutDeliveryMethodsTest` — must pass unchanged |
| Regression | Tax tests — preset mapping only, no formula change |
| Manual | 5-step merchant smoke: single US store, flat $5, TX tax 10%, dev storefront shows delivery option |

---

## 14. Accessibility & mobile

- Drawers already used — ensure focus trap, `aria-modal`, ESC close (partial today).
- Chip inputs: keyboard Enter to add, Backspace to remove last chip.
- Country/state selects: native `<select>` or accessible combobox; minimum 44px touch targets.
- Setup steps: visible step indicator (`aria-current="step"`).
- Error messages tied to fields (`aria-describedby`) — follow `tax_rate_form_fields` pattern.
- Mobile: wizard single column; sticky “Continue” footer.

---

## 15. Implementation stages (max 3 batches)

### Batch 1 — Language, overview, health (no business-logic changes)
- Rename merchant copy: zone → delivery area, method → delivery option
- Delivery setup overview page with setup-health card
- Plain-language checklist replacing metric-only cards
- Sidebar: group Delivery entry
- Diagnostic banner when methods exist but none match typical test address (read-only query)

**Effort:** ~3–5 days  
**Risk:** Low

### Batch 2 — Structured inputs + simple editors
- Country/state multi-select for delivery areas (reuse TaxCountryCatalog)
- Postal chip component
- Simple delivery-option editor with conditional pricing
- Hide location service fields from default create form
- `DeliveryAreaInputNormalizer` in controllers

**Effort:** ~5–8 days  
**Risk:** Medium (input edge cases, legacy data display)

### Batch 3 — Progressive disclosure + polish
- Advanced panels on all forms
- Guided 5-step wizard shell with review page
- Mobile/a11y pass
- Regression tests + merchant doc update

**Effort:** ~5–7 days  
**Risk:** Low if Batch 1–2 stable

---

## 16. Risks

| Risk | Mitigation |
|------|------------|
| Merchants with legacy comma data | Load-edit-convert in UI; don’t mass-migrate |
| Advanced users feel restricted | Advanced tab always available |
| Two UIs temporarily coexist | Single controller layer writing same models |
| Location vs zone confusion remains if copy wrong | Locked definitions in UI + review step |
| FedEx complexity bleeds into Simple mode | Carriers only in Advanced |

---

## 17. Explicit non-goals

- ❌ Merge `Location` and `ShippingZone` models
- ❌ Remove backend fields or routing services
- ❌ Change tax formulas, checkout totals, Stripe logic
- ❌ Change FedEx/USPS carrier behavior
- ❌ Phase 5R-2 Coupons
- ❌ Rebuild dev-test-storefront as merchant UI
- ❌ Fake “complete” buttons without persistence
- ❌ Reduce enterprise capabilities

---

## Appendix A — Files inspected

**Canonical context:** `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, `AGENTS.md`, `.cursor/rules/*`

**Routes:** `routes/web.php`, `routes/api.php`, `routes/carriers.php`, `routes/onboarding.php`

**Controllers:** `ShippingSettingsController`, `LocationController`, `TaxSettingsController`, `PaymentSettingsController`, `OnboardingController`, `DeveloperStorefrontSettingsController`

**Views:** `shippingAutomation.blade.php`, `shipping/tabs/*`, `shipping/partials/drawers.blade.php`, `locations.blade.php`, `settings/taxes.blade.php`, `partials/tax_rate_form_fields.blade.php`, `generalSettings.blade.php`, `layouts/user/user-sidebar.blade.php`

**Services:** `ShippingZoneMatcher`, `DeliveryOptionService`, `CheckoutShippingService`, `FulfillmentOriginRouter`, `LocationServiceAreaMatcher`, `TaxConfigurationService`, `TaxCalculator`, `CarrierOriginReadinessService`

**Support:** `App\Support\Tax\TaxCountryCatalog`

**Tests referenced:** `Phase6CheckoutDeliveryMethodsTest`, `ShippingZoneMatcherTest`

---

## Appendix B — How to instruct Cursor (for the merchant/developer)

Use this prompt pattern when ready to implement:

> **Enterprise UX simplification — Batch N only.**  
> Do not delete or rewrite backend shipping, tax, checkout, routing, or carrier logic.  
> Preserve all models and API contracts.  
> Simplify merchant UI through progressive disclosure, merchant terminology, structured inputs, and a guided Delivery setup flow per `docs/ux/MERCHANT_SETUP_AND_DELIVERY_UX_SIMPLIFICATION_PLAN.md`.  
> Simple mode must allow: one ship-from location, one delivery area, one flat-rate delivery option, basic tax — without exposing service areas, routing priority, or carrier accounts.

---

*End of plan — analysis only, no production code changed.*
