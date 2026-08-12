# Phase 6C-2 — FedEx Merchant-Owned Carrier Connection Foundation

## Summary

Phase 6C-2 establishes the first **merchant-owned carrier account connectivity** foundation using FedEx. The platform helps merchants connect and verify their own FedEx account — it does not pay FedEx charges, buy labels, or become the merchant’s carrier billing account.

## Implemented

- `FedExMerchantAccountConnectionService` for safe merchant-owned account save + verification
- `FedExMerchantConnectionResult` with merchant-friendly status outcomes
- FedEx wizard flow: ship-from origin → account details → connection check
- Merchant-owned ownership fields: `ownership_mode = merchant_owned`, `connection_owner = merchant`, `billing_owner = merchant`, `credentials_source = merchant_account`
- Account number masking in UI (`CarrierAccount::maskedAccountNumber()`)
- Carrier API event redaction for account numbers, tokens, secrets, email, phone
- FedEx blocked/carrier-support-required state preserved when external validation fails (account remains saved)
- FedEx card/status display with billing handled by merchant, rates testing only, labels not enabled
- USPS testing wording cleanup (`USPS testing tools` instead of `USPS public API` / OAuth jargon)
- Local/testing diagnostics collapsed as **Local testing details**

## Billing ownership rule

- FedEx billing stays between merchant and FedEx
- Platform does not pay carrier charges
- Platform does not buy labels in this phase
- `enabled_for_checkout = false` until live checkout rates are implemented

## Capabilities (this phase)

| Capability | FedEx merchant-owned |
|---|---|
| Rates testing | Yes after successful connection check (not checkout) |
| Labels | No |
| Tracking sync | No |
| Pickup | No |
| Checkout live rates | No |

## Key files

- `app/Services/Carriers/FedEx/FedExMerchantAccountConnectionService.php`
- `app/Services/Carriers/FedEx/DTO/FedExMerchantConnectionResult.php`
- `app/Support/CarrierAccountStatusPresenter.php`
- `app/Http/Controllers/CarrierConnectionWizardController.php`
- `resources/views/user_view/carrier_connection_wizard/show.blade.php`
- `resources/views/user_view/partials/carrier_account_card.blade.php`
- `tests/Feature/Phase6FedExMerchantOwnedConnectionTest.php`

## Deferred

- USPS merchant-owned account connectivity
- USPS labels, EPS/payment authorization
- FedEx labels and label purchase
- Carrier billing automation, platform-paid postage, shipping wallet
- UPS / DHL API integration
- Production/live carrier mode
- Pickup scheduling, tracking sync jobs
- Checkout live carrier rates
- Returns/exchanges, geocoding routing

## Related phases

- Phase 6C-1C: Carrier connection wizard foundation
- Phase 6C-1C-FIX: Raw carrier form removal
- Phase 6C-1A: FedEx sandbox carrier foundation (preserved)
- Phase 6C-2B: FedEx Merchant Credentials Mode (primary path — see `docs/phases/PHASE_6C_2B_FEDEX_MERCHANT_CREDENTIALS_MODE_REPORT.md`)

## Phase 6C-2A — Registration field validation + export cleanup

### Problem fixed

FedEx Credential Registration was failing with HTTP 422 `INVALID.INPUT.EXCEPTION` when merchants entered invalid country values such as `UN`. The platform now validates and normalizes registration input before save and before calling FedEx.

### Implemented

- `FedExRegistrationInputValidator` — rejects `UN`, `USA`, `United States`, invalid ZIP, non–2-letter US state codes; normalizes account number, city, state, postal code
- `CarrierCountryOptions` — FedEx country dropdown (United States / `US` only for this phase)
- Wizard FedEx details step uses country **select** instead of free-text 2-character input
- **Use selected ship-from location address** prefill link copies origin address into registration fields
- `FedExAccountRegistrationService` runs local validation before HTTP; skips FedEx registration call when input is invalid
- Registration payload uses normalized `countryCode: US`, 2-letter state, valid US ZIP
- Export route returns `redactedValidationSummary()` (not raw API payload) for FedEx support
- Legacy `storeFedExCarrierAccount` uses the same validator

### Merchant rules preserved

- Account remains saved when FedEx returns 422 after valid local input
- No fake “connected” state on FedEx rejection
- Full account number, tokens, secrets, phone, and email stay redacted in UI/logs/events
- Labels, billing, pickup, tracking, and checkout live rates remain deferred

### Key files (6C-2A)

- `app/Services/Carriers/FedEx/FedExRegistrationInputValidator.php`
- `app/Support/CarrierCountryOptions.php`
- `app/Services/Carriers/FedEx/FedExAccountRegistrationService.php` (`redactedValidationSummary`)
- `app/Http/Controllers/CarrierConnectionWizardController.php`
- `resources/views/user_view/carrier_connection_wizard/show.blade.php`
- `tests/Feature/Phase6FedExMerchantOwnedConnectionTest.php` (validation cases)
