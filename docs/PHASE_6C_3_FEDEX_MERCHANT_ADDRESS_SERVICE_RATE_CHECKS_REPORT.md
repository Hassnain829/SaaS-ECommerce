# Phase 6C-3 — FedEx Merchant Address / Service / Rate Checks

## Summary

Added FedEx Model B sandbox API checks using **merchant credentials only**. These are merchant-facing **test tools** — they do not buy labels, create shipments, change order totals, or enable checkout live rates.

## New services

| Service | FedEx API | Action logged |
|---------|-----------|---------------|
| `FedExAddressValidationService` | `POST /address/v1/addresses/resolve` | `fedex_address_validation` |
| `FedExServiceAvailabilityService` | `POST /availability/v1/packageandserviceoptions` | `fedex_service_availability` |
| `FedExRateQuoteService` | `POST /rate/v1/rates/quotes` | `fedex_rate_quote` |

Shared infrastructure:
- `FedExMerchantApiClient` — merchant OAuth + authenticated JSON calls
- `FedExMerchantCheckPresenter` — normalized UI summaries
- `FedExCarrierTestController` — store-scoped POST handlers

## UI

On the Carriers tab, merchant-credentials FedEx cards include a collapsed **FedEx testing tools** section with:
- Address check
- Service availability check
- Rate quote test

Results redirect to `/shippingAutomation?tab=carriers` with merchant-friendly summaries and collapsed redacted request/response diagnostics.

## Security

- Store-scoped carrier account resolution
- `settings.manage` permission required
- Merchant credentials mode only (Model B)
- OAuth tokens, secrets, full account numbers, and API keys are never logged or shown in UI

## Unchanged / deferred

- FedEx Model A remains deferred
- Labels, pickup, tracking sync, checkout live FedEx rates remain disabled
- No shipments created, no order total changes
- FedEx billing remains merchant-owned

## Tests

`tests/Feature/Phase6FedExMerchantApiChecksTest.php` — mocked HTTP integration tests for OAuth flow, logging redaction, permissions, and capability guards.
