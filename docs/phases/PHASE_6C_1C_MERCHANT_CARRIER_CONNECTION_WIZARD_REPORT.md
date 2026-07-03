# Phase 6C-1C — Merchant-Owned Carrier Connection Wizard + Ownership Cleanup

## Summary

Phase 6C-1C separates **platform testing carrier connections** from **merchant-owned carrier accounts**, adds a guided carrier connection wizard, and cleans up Shipping & Delivery UX so merchants are not misled by platform `.env` credentials.

## Implemented

- Explicit carrier account ownership fields on `carrier_accounts`
- `CarrierAccountStatusPresenter` for merchant-safe labels
- Carrier connection wizard (`/settings/shipping/carriers/connect`)
- Ship-from fulfillment location step with origin readiness checks
- USPS platform testing labeled honestly as **Platform testing connection**
- FedEx blocked state preserved as **Carrier support required**
- Main shipping page simplified: wizard CTA instead of raw developer forms
- Store-scoped wizard routes with `settings.manage` permission
- Capability labels: rates / labels / tracking / pickup (disabled states obvious)

## Ownership model

| Account type | ownership_mode | connection_owner | credentials_source |
|---|---|---|---|
| USPS platform testing | platform_testing | platform | platform_env |
| FedEx merchant sandbox | merchant_owned | merchant | merchant_encrypted |
| Manual/local delivery | manual | merchant | manual_entry |
| FedEx sandbox platform fallback | platform_testing | platform | platform_env |

## Deferred

- USPS labels, EPS, merchant-owned USPS credentials
- FedEx labels, production mode
- UPS / DHL API integration
- Checkout live carrier rates
- Tracking sync, pickup scheduling
- Returns/exchanges, carrier billing automation

## Key files

- `database/migrations/2026_06_03_010000_add_carrier_account_ownership_fields.php`
- `app/Models/CarrierAccount.php`
- `app/Support/CarrierAccountStatusPresenter.php`
- `app/Services/Carriers/CarrierConnectionWizardService.php`
- `app/Http/Controllers/CarrierConnectionWizardController.php`
- `resources/views/user_view/carrier_connection_wizard/*`
- `resources/views/user_view/partials/carrier_account_card.blade.php`
- `tests/Feature/Phase6MerchantCarrierConnectionWizardTest.php`

## Phase 6C-1C-FIX — Raw Carrier Form Removal

The old raw **Carriers & accounts** form was removed from the main Shipping & Delivery page.

- Carrier setup now starts from the guided wizard only (`Connect carrier account` CTA).
- Manual/local delivery uses the wizard path (`Add manual/local delivery` CTA) with countries and checkout availability fields.
- Existing carrier account cards remain visible in the aside hub using `carrier_account_card` partial.
- Legacy `POST /settings/shipping/carrier-accounts` redirects to the wizard with a merchant-friendly message (no account created).
- USPS package quote tester moved into a collapsed **Testing tools** section with informational-only labeling.
- USPS remains platform testing only; FedEx blocked state remains honest; UPS/DHL remain deferred.
- Labels, pickup, tracking sync, production/live mode, and checkout live carrier rates remain deferred.
- Normal merchant UI no longer exposes raw carrier setup fields (carrier/connection/status dropdowns, raw Add carrier account button).
