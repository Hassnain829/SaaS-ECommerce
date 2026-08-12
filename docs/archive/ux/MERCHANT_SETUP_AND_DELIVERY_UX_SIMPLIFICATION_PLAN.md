# Merchant Setup & Delivery UX Simplification Plan

**Project:** E_COMMERCE_OFFICE (SaaS-Static-Blade)  
**Date:** 2026-05-24  
**Revised:** 2026-07-02  
**Revision reason:** Implementation-readiness corrections before Batch 1  
**Status:** **Completed implementation** — Batches 1–3 shipped; sign-off correction pass complete (2026-05-24)
**Implementation docs:** [Batch 1](./BATCH_1_IMPLEMENTATION.md) · [Batch 2](./BATCH_2_IMPLEMENTATION.md) · [Batch 3](./BATCH_3_IMPLEMENTATION.md) · [Final acceptance report](./DELIVERY_UX_FINAL_ACCEPTANCE_REPORT.md)
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
| Tax appears in multiple setup flows | Delivery and Checkout & tax both expose editable tax — recreates duplication |

### Correct direction (locked)

**Do not delete, merge, or rewrite backend domain logic.**

Enterprise UX means:

- Same capabilities
- Organized into a **simple default journey**
- Advanced routing/carrier controls in **collapsed Advanced settings**
- Structured inputs (selectors, rule rows) writing to **existing models**
- **Tax remains a separate setup area** — not an editable step inside Delivery setup

Simplicity comes from **orchestration and progressive disclosure**, not loss of domain integrity.

### Batch 1 health diagnostics (locked)

Batch 1 setup-health checks must be **deterministic and configuration-based only**. Do **not** invent or assume a customer address. Do **not** check whether methods match a “typical test address.”

Address matching becomes a separate explicit tool in **Batch 3**: **Test a customer address** (read-only, merchant-entered input).

---

## 2. Page-level UX audit

Evidence from production Blade templates and merchant screenshots (Demo Digital store).

### 2.1 Locations (`settings.locations.index` → `user_view/locations.blade.php`)

| Field / control | Why confusing | Duplicates | Merchant risk | Recommended replacement | Backend unchanged |
|-----------------|---------------|------------|---------------|-------------------------|-------------------|
| Service countries (raw `US, CA`) | Looks like “where I deliver” | **Yes** — mirrors zone countries | Merchant fills zone + location differently; routing breaks | Hide in Simple mode; Advanced: **Countries this location can fulfill** (single-country context per area when editing routing) | `locations.service_countries[]` |
| Service regions (raw `CA, TX`) | Abbrev vs full name unclear; `CA` ambiguous | **Yes** — mirrors zone regions | TX/Texas mismatch blocks fulfillment routing | Advanced only; state multi-select for selected country | `locations.service_regions[]` |
| Service postal patterns | Undocumented prefix format | **Yes** — mirrors zone postal | Typo silently excludes origin | Advanced only; **Exact / Starts with** rule rows | `locations.service_postal_patterns[]` |
| Routing priority | Ops jargon | No | Wrong priority = unexpected origin | Advanced only; label **Fulfillment priority** | `locations.routing_priority` |
| Fulfill online orders | OK concept, buried in long form | No | Unchecked = no ship-from | Simple: default ON, visible toggle | `locations.fulfills_online_orders` |
| Offer pickup | OK but mixed with routing | No | Enables pickup methods later | Simple: visible when pickup method exists | `locations.pickup_enabled` |
| Country code (2-letter) on address | Merchant expects country name | No | Invalid codes | Searchable country select → ISO-2 | `locations.country_code` |
| State/Province free text | Inconsistent with zone regions | Partial | Format drift | State select when country has catalog | `locations.state` |
| Ship-from readiness badge | Good signal, no fix action inline | No | Carrier testing blocked | Link to “Complete address” inline | `CarrierOriginReadinessService` |
| Routing column in table | Dense dump of all advanced fields | No | Overwhelming scan | Summary line + “Advanced routing” expand | display only |
| Page also duplicated under Shipping → Locations tab | Two entry points, same complexity | **Yes** | “Which page is canonical?” | Single Simple entry; tab becomes Advanced shortcut | routes preserved |

**Advanced location copy rule:** Do **not** call location routing restrictions “where you deliver.” Use **Countries / States / ZIP coverage this location can fulfill** (fulfillment routing only).

### 2.2 Shipping zones (`shippingAutomation?tab=zones` → `drawers.blade.php`)

| Field / control | Why confusing | Duplicates | Merchant risk | Recommended replacement | Backend unchanged |
|-----------------|---------------|------------|---------------|-------------------------|-------------------|
| Label “Zone name” | Developer term | No | — | **Delivery area name** | `shipping_zones.name` |
| Countries (`US, CA` placeholder) | Raw comma string; multi-country in one form | **Yes** — location service countries | `CA` = Canada vs California ambiguity | **Simple: one country per delivery area**; Advanced: legacy multi-country editable | `shipping_zones.countries[]` |
| Regions (`California, Ontario`) | No TX/Texas guidance; cross-country mix | **Yes** | Silent non-match at checkout | State/province multi-select **for selected country only** | `shipping_zones.regions[]` |
| Postal patterns (`941*, 10001`) | Merchant must know wildcard syntax | **Yes** | Wrong zip = no delivery options | **Exact postal code** / **Starts with** rule rows; store `606*` internally | `shipping_zones.postal_patterns[]` |
| Sort order | Developer term | No | Rarely needed | Advanced: **Display order** | `shipping_zones.sort_order` |
| Active checkbox | OK | No | Inactive zone = empty checkout | Simple: “Available to customers” | `shipping_zones.is_active` |
| Coverage column in table | Long comma string | No | Unreadable | Human summary: “United States · Texas, California · 4 ZIP rules” | display only |

### 2.3 Delivery methods (`shippingAutomation?tab=methods` → `drawers.blade.php`)

| Field / control | Why confusing | Duplicates | Merchant risk | Recommended replacement | Backend unchanged |
|-----------------|---------------|------------|---------------|-------------------------|-------------------|
| Method name vs Customer label | Two names, unclear difference | No | Redundant or empty labels | Simple: one “Customer-facing name” + optional speed subtitle | `name`, `delivery_speed_label` |
| Zone dropdown | Requires understanding zones first | No | Method without zone = broken | Guided flow creates area first; show area name read-only in Simple | `shipping_methods.shipping_zone_id` |
| Carrier account | Shown before merchant needs it | No | Blocks simple flat-rate setup | Advanced; Simple uses safe manual provider resolution (Batch 2) | `shipping_methods.carrier_account_id` |
| Rate type (4 options at once) | All pricing fields visible | No | Wrong type selected | Conditional: Fixed / Free / Free over amount | `shipping_methods.rate_type` |
| Flat rate + Free over + Min/max order | All visible simultaneously | No | **Max order $10 blocked $50 cart** (observed) | Simple: price OR free-over only; min/max Advanced | `flat_rate`, `free_over_amount`, `min_order_amount`, `max_order_amount` |
| Checkout vs Active toggles | Two similar flags | No | Method not at checkout; flags can diverge | **New Simple records:** unified **Available to customers**; **Existing:** show mismatch status, do not silently overwrite | `enabled_for_checkout`, `is_active` |
| Min/max days | OK for merchants | No | — | Simple: “Delivery estimate” single range | `estimated_min_days`, `estimated_max_days` |
| Sort order | Developer term | No | — | Advanced: **Display order** | `shipping_methods.sort_order` |
| Drawer sections (5 blocks) | Cognitive overload | No | Abandon setup | Simple: 3 questions + review; rest Advanced | — |

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
| Four separate checkboxes | No preset grouping | No | Wrong combination | Map presets → existing booleans (in **Checkout & tax** only) | `enabled`, `prices_include_tax`, etc. |
| Calculation address (read-only) | Looks editable | No | — | Helper text only; hide label in Simple | `calculation_address` |
| Tax rate form | **Good pattern** — country select + region select | No | — | **Reuse this pattern for delivery areas** (Batch 2) | `tax_rates.*` |
| Priority in advanced | OK | No | — | Keep collapsed | `tax_rates.priority` |

**Note:** Tax UX is **closer to target** than Shipping/Locations. Tax configuration lives under **Checkout & tax** — never as an editable step inside the Delivery wizard. Delivery review may show read-only tax summary + **Edit tax settings** link only.

### 2.6 Navigation & setup visibility

| Area | Problem | Recommendation |
|------|---------|----------------|
| Settings sidebar | 7 equal items; Locations separate from Shipping | **Delivery** hub + **Checkout & tax** (separate) |
| `generalSettings` | “Configure shipping & delivery” dumps to 5-tab page | Link to **Delivery** overview with health card |
| Onboarding (3 steps) | Products only; no delivery/tax/payment setup | Post-onboarding checklist links to Delivery + Checkout & tax (not in onboarding rewrite yet) |
| Shipping overview checklist | Backend terms; metric cards without actions | Plain-language summary + deterministic health actions |
| Dev test storefront | Developer simulator, not merchant UI | Out of scope for merchant redesign; keep separate |

### 2.7 Developer test storefront (reference only)

Observed: platform checkout skips delivery when `delivery_options: []` — often merchant config (max order, region mismatch). **Batch 1** must not guess addresses. **Batch 3** **Test a customer address** tool gives deterministic explanations without writing data.

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
| **Delivery area** (`ShippingZone`) | Customer checkout eligibility | **Always configure** (one country per area) |
| **Location service area** (`Location.*service_*`) | Which ship-from location handles an order | **Hidden** — default: main location serves all delivery areas |
| **Delivery option** (`ShippingMethod`) | Price/speed shown at checkout | **Always configure** |
| **Carrier account** | Label/rates provider | **Optional** — manual default via safe resolution (Batch 2) |
| **Tax** (`TaxSetting`, `TaxRate`) | Checkout tax behavior | **Separate area: Checkout & tax** — not in Delivery wizard |

### Safe defaults (no migration)

1. New locations: `service_countries`, `service_regions`, `service_postal_patterns` = **empty** → matcher treats as “serve store default / all destinations” per `LocationServiceAreaMatcher`.
2. Simple setup never writes location service fields unless Advanced routing opened.
3. Delivery area remains **source of truth** for checkout `delivery-options` API.
4. Simple delivery area: **one country per area**; additional countries = **Add another delivery area**.

### Known backend inconsistency (document for Batch 2)

- `ShippingZoneMatcher` maps US state abbreviations ↔ full names.
- `LocationServiceAreaMatcher` still uses exact region string match — Advanced routing UI should normalize on save; no matcher changes in Batch 1.

---

## 4. Locked merchant-facing definitions

| Internal model | Merchant term | Merchant question |
|----------------|---------------|-------------------|
| `Location` | **Ship-from location** | Where do you keep and ship products from? |
| `ShippingZone` | **Delivery area** | Where do you deliver? |
| `ShippingMethod` | **Delivery option** | How can customers receive their order? |
| `CarrierAccount` | **Delivery provider** | Who delivers the package? (optional) |
| `TaxSetting` + `TaxRate` | **Checkout tax** | Should tax be added, and where? (**separate setup area**) |

**Do not merge models.** UI terminology only.

---

## 5. Proposed information architecture

### Settings navigation (target — locked)

```
SETTINGS
├── Store details          (generalSettings — profile, currency, timezone)
├── Payments               (settings.payments.*)
├── Checkout & tax         (settings.taxes.index — tax presets, rates; checkout mode summary)
├── Delivery               (hub — default landing; NO editable tax step)
├── Test storefront
└── Team & security
```

### Delivery default view (first-time merchant)

Must answer:

- Where do orders ship from?
- Where do you deliver?
- What do customers see at checkout?
- Is delivery setup ready?

Contains:

- Setup overview
- Ship-from summary
- Delivery-area summary
- Delivery-option summary
- Optional delivery-provider summary
- Clear setup-health actions (deterministic, config-based)
- **Read-only tax summary** (e.g. “Tax is off” / “Tax added at checkout”) + **Edit tax settings** link → Checkout & tax

**Do not** display five equal-weight advanced tabs as the default first-time experience.

### Delivery advanced (collapsed entry)

- Ship-from locations
- Delivery areas
- Delivery options
- Carriers
- Routing rules

### Route compatibility (no breaking changes)

| Current route | Future role |
|---------------|-------------|
| `shippingAutomation` | **Delivery** hub / setup overview (same URL OK) |
| `settings.locations.index` | Advanced → Ship-from locations |
| `settings.taxes.index` | **Checkout & tax** (unchanged URL; tax edits only here) |
| `settings.shipping.zones.*` | Batch 2+ simple/advanced editors; Batch 3 wizard Step 2 |
| `settings.shipping.methods.*` | Batch 2+ simple/advanced editors; Batch 3 wizard Step 3 |
| `shipping.carriers.connect.*` | Advanced → Carriers |

**Locations removed from top-level sidebar** — accessed via Delivery hub or Advanced.

---

## 6. Guided setup flow: “Delivery setup” (four steps — locked)

**Tax is not a step.** Delivery wizard has exactly four steps. Tax configuration remains under **Checkout & tax**.

### Step 1 — Ship from

**Heading:** Where do you ship from?

Visible:
- Location name
- Address (line, city, state select, postal, country select)
- “Fulfill online orders” (default ON)

Hidden (Advanced):
- Routing priority
- Service countries/regions/postal patterns
- Pickup (until pickup delivery option added)

Writes to: `Location` via existing `LocationController` or Batch 3 orchestrator (**Batch 3 only** — not Batch 1).

### Step 2 — Deliver to

**Heading:** Where do you deliver?

**Simple mode (locked): one delivery area = one country**

- Select **one country** first (searchable select; display name, store ISO-2)
- Coverage mode for that country:
  - Entire country
  - Selected states/provinces (loaded for **that country only**)
  - Selected ZIP/postal rules (**Exact postal code** / **Starts with** rows)
- To deliver to another country: **Add another delivery area**

Hidden (Advanced):
- Multi-country on single zone (legacy edit only)
- Sort order / display order
- Overlapping zone strategy docs

Writes to: `ShippingZone` via existing zone store/update endpoints (**Batch 2+**).

### Step 3 — Delivery option

**Heading:** What should customers see at checkout?

Visible:
- Customer-facing name (e.g. “Standard delivery”)
- Delivery speed label (e.g. “2–4 business days”)
- Delivery price: Fixed price | Free | Free over order amount (conditional fields)
- Delivery estimate (days)
- **New Simple records:** single **Available to customers** → sets `is_active` and `enabled_for_checkout` true; off requires confirmation before both false

Hidden (Advanced):
- Separate Active / Show at checkout toggles
- Min/max order eligibility
- Delivery provider select (Simple uses safe manual resolution — see §8)
- Display order, description

**Manual delivery provider (Batch 2 — locked safe rule):**

When saving a simple delivery option:

1. Reuse the store’s existing active manual delivery `CarrierAccount` when available.
2. Never use another store’s carrier account.
3. Never create duplicate manual carrier accounts.
4. If no valid manual account exists: use existing project service/convention if proven by repository inspection, **or** fail with clear setup message if auto-provisioning is not supported.
5. Do not modify FedEx or USPS accounts.
6. Do not require a live carrier for flat-rate simple delivery.
7. Review summary shows **Delivery provider: Manual delivery** when used.

**Repository inspection note (pre-Batch 2):** Current codebase exposes manual account creation via `ShippingSettingsController@storeCarrierAccount` and `CarrierConnectionWizardController` — **no dedicated `ensureManualCarrierAccount` service was found at audit time.** Implementers must inspect repositories/services before assuming automatic provisioning; do not invent implementation without evidence.

Writes to: `ShippingMethod` + `carrier_account_id` per rules above (**Batch 2+**).

### Step 4 — Review and activate

**Heading:** Review your delivery setup

Plain-language summary example:

> Orders ship from **Main location** (New York, NY).  
> You deliver to **United States — Texas and California**.  
> Customers see **Standard delivery** for **$5.00** (free over **$50.00**).  
> Delivery provider: **Manual delivery**.  
> Tax: **Added at checkout** — [Edit tax settings]

Tax line is **read-only** with link to `settings.taxes.index`. **Do not edit or save tax settings from this step.**

Each delivery section: **Edit** → deep-link to step.

---

## 7. Simple mode vs Advanced mode

### Simple mode (default)

| Area | Visible fields |
|------|----------------|
| Ship-from | Name, address, fulfill online |
| Delivery area | **One country**; entire country OR selected states OR ZIP/postal rules (Exact / Starts with) |
| Delivery option | Name, speed, delivery price type, price/threshold, estimate, **Available to customers** (new records) |
| Tax | **Not in Delivery flow** — configure under Checkout & tax |
| Carriers | Manual delivery implied via safe resolution; no FedEx/USPS required |

### Advanced mode (collapsed **Advanced settings** or **Open advanced delivery settings**)

| Area | Visible fields |
|------|----------------|
| Locations | Multiple locations, fulfillment priority, service-area routing, pickup |
| Delivery areas | Multiple areas, **legacy multi-country zones**, display order, overlapping rules |
| Delivery options | Separate **Active** / **Show at checkout**, delivery provider mapping, min/max order, all rate types |
| Carriers | FedEx/USPS integrator, credentials, validation workspace |
| Routing | Location fulfillment constraints (not “where you deliver”) |

### Delivery option flag behavior (locked)

**New Simple delivery option**

- Single control: **Available to customers**
- On save (new record): may set `is_active = true` and `enabled_for_checkout = true`
- Turning off: may set both false **only after clear confirmation**

**Existing records with mismatched flags**

- Do **not** silently overwrite
- Show visible status:
  - “This option is active but hidden from checkout.”
  - “This option is shown at checkout but currently inactive.”
- Provide explicit resolution controls

**Advanced mode**

- Keep separate **Active** and **Show at checkout** controls

**Rule:** Advanced must not be required for a working single-location US store with flat-rate shipping.

---

## 8. Structured input design (Batch 2+ — not Batch 1)

Reuse **`TaxCountryCatalog`** and patterns from `tax_rate_form_fields.blade.php`.

### Countries (Simple delivery area)

- **One country per delivery area** — searchable single select
- Display: “United States”
- Store: `US` in `shipping_zones.countries[]` (array of one element for new Simple records)
- **No multi-country selector in Simple mode**
- Advanced: existing multi-country zone records remain editable (legacy)

### Regions

- After country selection: multi-select of states/provinces **for that country only**
- Display: “Texas”
- Store: `TX` (normalized)
- Never ask merchant to type `TX` vs `Texas`
- Never mix regions from different countries in one Simple form

### Postal coverage rules (ZIP/postal coverage rules)

Merchants must **not** type wildcard characters.

Each rule is one row/chip with explicit type:

| UI control | Example UI | Internal storage |
|------------|------------|------------------|
| **Exact postal code** | `75002` | `75002` |
| **Starts with** | `606` | `606*` |

Requirements:

- One rule per chip/row
- Visibly label **Exact postal code** vs **Starts with**
- Uppercase normalization where relevant
- Remove duplicates
- Validation based on selected country where practical
- Clear invalid-input message
- Legacy values such as `606*` load as **Starts with: 606**

### Delivery price

- Radio: Fixed price | Free | Free over amount
- Show only relevant numeric field(s)
- Map to `rate_type`: `flat`, `free`, `flat` + `free_over_amount`

### Controller boundary (Batch 2)

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
| `locations.service_*` | — | Structured routing fields |
| `locations.routing_priority` | — | Fulfillment priority |
| `shipping_zones.name` | Auto-generated or simple label | Editable |
| `shipping_zones.countries` | **Single country select** | Multi-country (legacy) |
| `shipping_zones.regions` | State multi-select (selected country) | Same + legacy cross-country display |
| `shipping_zones.postal_patterns` | Exact / Starts-with rule rows | Same |
| `shipping_zones.sort_order` | — | Display order |
| `shipping_methods.name` | Customer-facing name | + internal name |
| `shipping_methods.delivery_speed_label` | Speed text | Speed text |
| `shipping_methods.rate_type` | Delivery price radio | Full rate type select |
| `shipping_methods.flat_rate` | Price input | Price input |
| `shipping_methods.free_over_amount` | Threshold input | Threshold input |
| `shipping_methods.min/max_order_amount` | — | Optional limits with helper |
| `shipping_methods.shipping_zone_id` | Auto from Step 2 | Dropdown |
| `shipping_methods.carrier_account_id` | Safe manual resolution | Delivery provider select |
| `shipping_methods.is_active` | **Available to customers** (new only) | Active toggle |
| `shipping_methods.enabled_for_checkout` | **Available to customers** (new only) | Show at checkout toggle |
| `tax_settings.*` | — (Delivery review: read-only summary + link) | Checkout & tax presets |

---

## 10. Locked merchant copy

### Headings

- Where do you ship from?
- Where do you deliver?
- What should customers see at checkout?
- Review your delivery setup

### Status actions

- Add ship-from address
- Choose a delivery area
- Add a delivery option
- Fix checkout visibility
- Test a customer address *(Batch 3 tool)*
- Open advanced delivery settings
- Edit tax settings *(links to Checkout & tax)*

### Terminology

| Old / internal | Merchant-facing |
|----------------|-----------------|
| Shipping zone | **Delivery area** |
| Shipping method | **Delivery option** |
| Fulfillment origin | **Ship-from location** |
| Carrier account | **Delivery provider** |
| Rate type | **Delivery price** |
| Sort order | **Display order** |
| Postal patterns | **ZIP/postal coverage rules** |

### Advanced location copy (routing — not delivery)

- Countries this location can fulfill
- States/provinces this location can fulfill
- ZIP/postal coverage for this location
- Fulfillment priority

**Never** describe location service areas as “where you deliver.”

---

## 11. Route / controller / service impact map

### Batch 1 — read-only / presentation only

| Artifact | Purpose | Writes data? |
|----------|---------|--------------|
| `DeliverySetupStatusService` (proposed) | Deterministic health checks from existing models | **No** |
| Delivery hub Blade (overview) | Summaries, health cards, edit links | **No** |
| Sidebar / copy updates | Terminology, navigation grouping | **No** |

**Batch 1 prohibited:** new write endpoints, wizard persistence, input normalizer, structured geo components, carrier auto-creation, tax preset saving, matching/routing service changes.

### Batch 2 — structured inputs and simple editors

| Artifact | Purpose |
|----------|---------|
| `DeliveryAreaInputNormalizer` | UI → existing list fields |
| Simple delivery-area / option / ship-from editors | One-country areas, postal rules, conditional pricing |
| Manual provider resolution helper | Inspect repo first; reuse store manual account per §6 Step 3 rules |
| `ShippingSettingsController` / `LocationController` | Accept normalized arrays; hide routing fields in Simple POST |

### Batch 3 — guided flow and advanced polish

| Artifact | Purpose |
|----------|---------|
| `DeliverySetupController` (optional) | Four-step wizard orchestration |
| **Test a customer address** tool | Read-only diagnostic using existing matchers/services |
| Advanced panels, mobile/a11y | Progressive disclosure |

### Existing — no logic rewrite

| Artifact | Batch 1 | Batch 2+ |
|----------|---------|----------|
| `ShippingZoneMatcher` | **No change** | **No change** |
| `DeliveryOptionService` | **No change** | **No change** |
| `CheckoutShippingService` | **No change** | **No change** |
| `FulfillmentOriginRouter` | **No change** | **No change** |
| `LocationServiceAreaMatcher` | **No change** | Normalize on save in UI only |
| `TaxCalculator` / `TaxSettingsController` | **No change** | Tax edits stay in Checkout & tax only |

---

## 12. Setup-health checks (deterministic — Batch 1)

Allowed Batch 1 health checks (configuration-based only):

| Check | Status when failed |
|-------|-------------------|
| No active ship-from location | Needs ship-from address |
| Main/default location address incomplete | Needs address |
| No location enabled for online fulfillment | Fix fulfillment setting |
| No active delivery area | Not configured |
| Delivery area has no country | Incomplete delivery area |
| No active delivery option | Not configured |
| Delivery option has no delivery area | Misconfigured |
| Option active but hidden from checkout | Fix checkout visibility |
| Option shown at checkout but inactive | Fix checkout visibility |
| Min order > max order | Invalid eligibility |
| Negative price or threshold | Invalid pricing |
| No valid manual/default provider mapping where required | Needs delivery provider setup |

**Not allowed in Batch 1:** address-matching simulation, assumed test addresses, checkout API calls that mutate state.

---

## 13. Test a customer address (Batch 3 — locked)

Separate tool; **read-only**; does not change data.

Merchant enters:

- Country
- State/province
- Postal code
- Optional order subtotal

Tool explains (using existing resolution services):

- Matched delivery area(s)
- Available delivery options (with price)
- Unavailable options with **reason per option**

Example output:

- Standard delivery — available for $5
- Express delivery — unavailable because minimum order is $50
- Local delivery — unavailable outside selected ZIP codes

---

## 14. Blade / JS files likely affected

### Batch 1 — presentation only (no new write behavior)

- `resources/views/layouts/user/user-sidebar.blade.php`
- `resources/views/user_view/shippingAutomation.blade.php`
- `resources/views/user_view/shipping/tabs/overview.blade.php`
- `resources/views/user_view/generalSettings.blade.php`
- `resources/views/user_view/locations.blade.php` (copy/links only)
- New: Delivery hub summary partials (read-only)

### Batch 2 — structured inputs + simple editors

- `resources/views/user_view/shipping/partials/drawers.blade.php`
- `resources/views/user_view/shipping/tabs/zones.blade.php`
- `resources/views/user_view/shipping/tabs/methods.blade.php`
- `resources/views/user_view/locations.blade.php`
- New: `resources/views/components/geo/*`
- `app/Http/Controllers/ShippingSettingsController.php`
- `app/Http/Controllers/LocationController.php`

### Batch 3 — wizard + diagnostic + polish

- New: `resources/views/user_view/delivery/*` (four-step wizard)
- New: Test a customer address view + read-only controller action
- `resources/views/user_view/shipping/tabs/carriers.blade.php`
- Mobile drawer accessibility pass

### Explicitly out of scope

- FedEx validation workspace blades
- `dev-test-storefront/*` (separate developer tool)
- Phase 5R-2 Coupons

---

## 15. Backward-compatibility plan

1. **URLs:** Keep `shippingAutomation`, `settings.locations.index`, `settings.taxes.index` working.
2. **API:** No checkout API changes.
3. **Data:** Existing comma-separated and multi-country zone values remain valid; editors parse and display correctly.
4. **Legacy multi-country zones:** Remain editable in Advanced; Simple editor creates one-country areas.
5. **Legacy wildcard postal patterns:** Load as **Starts with** rules in UI.
6. **Mismatched method flags:** Preserved until merchant explicitly resolves.
7. **Permissions:** Same `settings.view` / `settings.manage` middleware.
8. **Snapshots:** Order/checkout snapshots unchanged.

**Migrations required:** **None** for Batch 1–3 as described.

---

## 16. Test strategy

### Batch 1

- Existing URLs still work
- Delivery hub is store-scoped
- Staff/view permissions remain correct
- Setup-health statuses reflect actual configuration (deterministic checks only)
- **No database writes from overview page**
- Old advanced pages remain reachable
- Existing checkout/shipping/tax tests remain unchanged and pass

### Batch 2

- One country per Simple delivery area
- Region options belong to selected country only
- Country name displays while ISO-2 stores
- Exact postal rule stores exact code
- Starts-with rule stores compatible prefix pattern (e.g. `606*`)
- Legacy wildcard values load as Starts with
- Duplicate postal rules removed
- Existing mismatched active/checkout flags **not overwritten silently**
- Manual carrier account reused for store
- Duplicate manual account not created
- No cross-store provider use
- Existing multi-country advanced zone remains editable

### Batch 3

- Four-step Delivery wizard (Ship from → Deliver to → Delivery option → Review)
- Review summary matches persisted data
- Tax is summary/link only — no tax writes from wizard
- **Test a customer address** returns deterministic explanations
- No writes from address diagnostic
- Keyboard/mobile/accessibility coverage
- Existing checkout delivery resolution unchanged

### Regression (all batches)

- `Phase6CheckoutDeliveryMethodsTest` — must pass unchanged
- Tax formula tests — no changes from Delivery UX work

---

## 17. Implementation stages (max 3 batches — locked)

### Batch 1 — Presentation and information architecture only

**Allowed:**

- Merchant-facing terminology
- Sidebar/navigation grouping
- Delivery hub / overview
- Setup-health cards (deterministic, read-only)
- Plain-language summaries
- Direct links to existing edit pages
- Advanced settings entry
- Responsive and accessible presentation
- Existing URLs remain functional

**Prohibited:**

- No new write endpoints
- No guided-form persistence
- No zone/location/method orchestration
- No tax preset saving
- No input normalizer
- No country/state/postal components
- No automatic carrier account creation
- No changes to matching, routing, checkout, or tax services

**Effort:** ~3–5 days  
**Risk:** Low

### Batch 2 — Structured inputs and simple editors

- One-country delivery area editor
- Country and state/province structured inputs
- Exact / Starts-with postal rules
- Simple delivery-option conditional pricing
- Simple ship-from form
- Default hiding of routing fields
- Safe manual provider resolution (after repository inspection)
- Compatibility with legacy records

**Effort:** ~5–8 days  
**Risk:** Medium

### Batch 3 — Guided flow and advanced polish

- **Four-step** Delivery setup wizard
- Review and activation summary (tax read-only + link)
- **Test a customer address** tool
- Progressive disclosure for advanced controls
- Mobile and accessibility polish
- Regression tests and documentation

**Effort:** ~5–7 days  
**Risk:** Low if Batch 1–2 stable

---

## 18. Risks

| Risk | Mitigation |
|------|------------|
| Simple UI accidentally overwriting advanced records | Load-edit with explicit Advanced path; flag mismatches; no silent merges |
| Ambiguous existing multi-country zones | Advanced-only edit; Simple creates one-country areas; clear summary text |
| Mismatched active/checkout flags | Status messages + explicit resolution; never silent overwrite on existing |
| Duplicate manual carrier accounts | Reuse store manual account; repository-verified creation path only |
| Legacy wildcard postal patterns | Load as Starts with; never require merchant to type `*` |
| Tax appearing in two setup flows | **Tax only in Checkout & tax**; Delivery review read-only + link |
| Health checks assuming customer addresses | Batch 1 config-only; Batch 3 separate diagnostic tool |
| Merchants with legacy comma data | Parse on load; display structured; don’t mass-migrate |
| Advanced users feel restricted | Advanced entry always visible |
| FedEx complexity bleeds into Simple mode | Carriers only in Advanced |
| Assuming manual account auto-provisioning exists | **Repository inspection required before Batch 2** |

---

## 19. Explicit non-goals

- ❌ Merge `Location` and `ShippingZone` models
- ❌ Remove backend fields or routing services
- ❌ Change tax formulas, checkout totals, Stripe logic
- ❌ Change FedEx/USPS carrier behavior
- ❌ Phase 5R-2 Coupons
- ❌ Rebuild dev-test-storefront as merchant UI
- ❌ Fake “complete” buttons without persistence
- ❌ Reduce enterprise capabilities
- ❌ Editable tax step inside Delivery wizard
- ❌ Business-logic implementation during documentation-only tasks
- ❌ Unconditional auto-creation of manual carrier accounts without verified service

---

## Appendix A — Files inspected

**Canonical context:** `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, `AGENTS.md`, `.cursor/rules/*`

**Routes:** `routes/web.php`, `routes/api.php`, `routes/carriers.php`, `routes/onboarding.php`

**Controllers:** `ShippingSettingsController`, `LocationController`, `TaxSettingsController`, `PaymentSettingsController`, `OnboardingController`, `DeveloperStorefrontSettingsController`, `CarrierConnectionWizardController`

**Views:** `shippingAutomation.blade.php`, `shipping/tabs/*`, `shipping/partials/drawers.blade.php`, `locations.blade.php`, `settings/taxes.blade.php`, `partials/tax_rate_form_fields.blade.php`, `generalSettings.blade.php`, `layouts/user/user-sidebar.blade.php`

**Services:** `ShippingZoneMatcher`, `DeliveryOptionService`, `CheckoutShippingService`, `FulfillmentOriginRouter`, `LocationServiceAreaMatcher`, `TaxConfigurationService`, `TaxCalculator`, `CarrierOriginReadinessService`

**Support:** `App\Support\Tax\TaxCountryCatalog`

**Manual provider audit (2026-07-02):** `ShippingSettingsController@storeCarrierAccount`, `CarrierConnectionWizardController` — no dedicated ensure-manual service found; Batch 2 must re-verify.

**Tests referenced:** `Phase6CheckoutDeliveryMethodsTest`, `ShippingZoneMatcherTest`

---

## Appendix B — How to instruct Cursor (for the merchant/developer)

**Batch 1 only:**

> Enterprise UX simplification — **Batch 1 only (presentation)**.  
> Do not change persistence, business logic, matching, routing, tax, checkout, payments, or carriers.  
> Update terminology, sidebar, Delivery hub, deterministic read-only health cards, summaries, and edit links per `docs/ux/MERCHANT_SETUP_AND_DELIVERY_UX_SIMPLIFICATION_PLAN.md`.  
> Tax stays under Checkout & tax — not in Delivery wizard.  
> No structured inputs, no wizard writes, no address-matching diagnostics in Batch 1.

**Batch 2+:**

> Follow revised plan: one country per Simple delivery area; Exact/Starts-with postal rules; safe manual provider resolution after repository inspection; four-step Delivery wizard deferred to Batch 3.

---

*End of plan — Batches 1–3 implemented per linked implementation and acceptance docs.*
