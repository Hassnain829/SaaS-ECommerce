# Phase 6C-2C — Shipping & Delivery UX Simplification + Model B FedEx Polish

## Summary

Rebuilt the merchant **Shipping & Delivery** page from a cluttered two-column layout into an enterprise tabbed experience with progressive disclosure. **FedEx Model B (merchant credentials)** remains the active path. **FedEx Model A / Official Integrator Provider** remains deferred.

## What changed

### Page architecture

- `resources/views/user_view/shippingAutomation.blade.php` is now a tabbed shell:
  - **Overview** (default)
  - **Carriers**
  - **Zones**
  - **Methods**
  - **Locations**
- Enterprise header with summary counts and CTAs: Add delivery method, Connect carrier, Manage locations.
- Tab state persists via `?tab=` query string and `localStorage`.

### New partials

| Partial | Purpose |
|---------|---------|
| `shipping/tabs/overview.blade.php` | Setup checklist, summary cards, recommended next step |
| `shipping/tabs/carriers.blade.php` | FedEx, USPS Sandbox Tools, Manual/Local sections |
| `shipping/tabs/zones.blade.php` | Zone table + Add zone (drawer) |
| `shipping/tabs/methods.blade.php` | Method list + Add method (drawer) |
| `shipping/tabs/locations.blade.php` | Fulfillment origin summary |
| `shipping/partials/fedex_merchant_card.blade.php` | Polished Model B FedEx card |
| `shipping/partials/drawers.blade.php` | Slide-over add/edit zone and method forms |

### Carrier UX

- **FedEx Merchant Account** — primary section with environment/status/merchant-owned badges, masked account/API key, capability chips, actions, collapsed technical details.
- **USPS Sandbox Tools** — clearly labeled platform testing; not merchant-owned.
- **Manual / Local Delivery** — visually distinct internal fulfillment option.

### Forms & JS

- Zone and method add/edit forms moved into hidden slide-over drawers (not visible on page load).
- Vanilla JS handles tabs, drawers, edit population, conditional method fields, and submit loading states.
- Carrier test/disable/remove forms remain standard POST redirects (no forced AJAX).

## What did not change (backend)

- No new FedEx APIs.
- No Model A, labels, pickup, tracking sync, or checkout live FedEx rates.
- Existing routes and wizard flows unchanged.
- Merchant credentials remain encrypted; secrets masked in UI.

## Tests

- Updated `Phase6USPSPublicApiFoundationTest` for **USPS Sandbox Tools** label.
- Added `Phase6ShippingDeliveryUxTest` for tabbed layout, section separation, masking, and drawer defaults.

## Explicitly deferred

- FedEx Model A / integrator registration merchant UI
- FedEx labels, pickup, tracking sync, checkout live rates
- USPS merchant-owned labels
- UPS, DHL
- AJAX connection testing (redirect POST routes retained)
- Drag-to-reorder methods, toast system beyond flash messages
