# Phase 6C-2B — FedEx Merchant Credentials Mode

## Summary

Phase 6C-2B replaces FedEx Credential Registration as the **primary** merchant connection path with **Merchant Credentials Mode**. Merchants connect their own FedEx Developer API key, secret, and account number. The platform encrypts credentials and verifies connection via merchant OAuth only.

## Why Credential Registration was replaced

FedEx Credential Registration (`/registration/v2/address/keysgeneration`) repeatedly returned HTTP 422 `INVALID.INPUT.EXCEPTION` even after address/country validation fixes. That flow depends on FedEx integrator/provider validation and FedEx support — it is not a reliable primary merchant setup model for this SaaS platform.

## Implemented

- FedEx merchant credentials wizard step (API key, secret, account number, environment)
- Encrypted storage in `carrier_accounts.credentials_encrypted` (`client_id`, `client_secret`)
- `FedExMerchantCredentialsOAuthService` — OAuth using merchant credentials only (`grant_type=client_credentials`)
- `FedExMerchantCredentialsInputValidator`
- Connection check skips platform OAuth and Credential Registration for merchant credentials accounts
- Masked account number and API key in UI
- Event redaction for secrets, tokens, full client ID, full account number
- `credentials_source = merchant_encrypted`, `connection_mode = fedex_merchant_credentials`
- Labels, pickup, tracking, checkout live rates remain disabled
- FedEx billing remains merchant-owned

## Legacy (local/testing only)

- Credential Registration code retained for legacy integrator diagnostics
- Hidden from normal merchant UI for merchant credentials accounts
- Labeled **Legacy FedEx integrator registration diagnostic**

## Key files

- `app/Services/Carriers/FedEx/FedExMerchantCredentialsOAuthService.php`
- `app/Services/Carriers/FedEx/FedExMerchantCredentialsInputValidator.php`
- `app/Services/Carriers/FedEx/FedExMerchantAccountConnectionService.php`
- `app/Services/Carriers/FedEx/FedExCarrierProvider.php`
- `app/Models/CarrierAccount.php`
- `resources/views/user_view/carrier_connection_wizard/show.blade.php`
- `tests/Feature/Phase6FedExMerchantCredentialsModeTest.php`

## Billing rule

FedEx billing stays between merchant and FedEx. The platform does not pay FedEx charges or buy labels.

## Deferred

- FedEx labels and label purchase
- Carrier billing automation, platform-paid postage
- Pickup scheduling, tracking sync, checkout live rates
- USPS merchant-owned, UPS, DHL

## Related

- Phase 6C-2: FedEx merchant-owned connection foundation
- Phase 6C-2A: Registration field validation (legacy path)
