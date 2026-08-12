# Phase 6C-1B-USPS — Public API Foundation Report

**Date:** 2026-06-02  
**Status:** Core functional completion for OAuth, address validation, and domestic test rate quotes

## What was implemented

- Provider-neutral USPS integration under `app/Services/Carriers/USPS/`
- Platform-level USPS OAuth (`client_credentials`) with cached tokens and safe `carrier_api_events` logging
- USPS carrier account cards on Shipping & Delivery (testing environment only)
- Optional address validation against default origin location during connection test
- Package builder + domestic RETAIL rate quote tester (informational only)
- `shipment_packages` and `carrier_rate_quotes` store-scoped persistence
- Production guardrail: TEM base URL (`apis-tem.usps.com`) only allowed in `local` / `testing`

## Explicitly deferred

- USPS label purchase
- EPS / platform payment authorization
- Pickup scheduling
- Production/live USPS enablement
- Checkout live-rate automation (checkbox saves preference only)
- Merchant-owned label purchase flows

## Environment variables

```env
USPS_ENABLED=false
USPS_ENVIRONMENT=testing
USPS_BASE_URL=https://apis-tem.usps.com
USPS_CONSUMER_KEY=
USPS_CONSUMER_SECRET=
USPS_CRID=
USPS_MASTER_MID=
USPS_LABELER_MID=
USPS_LABELS_ENABLED=false
USPS_PLATFORM_LABEL_PURCHASE=false
```

## Manual testing checklist

1. Set USPS env values in local `.env` (never commit secrets)
2. `php artisan migrate` and `php artisan optimize:clear`
3. Open **Settings → Shipping & Delivery**
4. Confirm USPS section appears; no Consumer Secret visible
5. Create USPS testing account with default origin location
6. **Test connection** → OAuth should succeed; address validation runs separately
7. Use **USPS package quote tester** → quote appears as informational record
8. Confirm no label purchase / EPS / pickup controls
9. Confirm order/payment/fulfillment status unchanged

## Commands run

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate
php artisan test --filter=Phase6USPSPublicApiFoundationTest
php artisan test
npm.cmd run build
cd dev-test-storefront && npm.cmd run build
```

## FedEx note

FedEx Phase 6C-1A code remains intact. FedEx Credential Registration is still paused/blocked by FedEx validation; USPS work does not remove or replace FedEx.
