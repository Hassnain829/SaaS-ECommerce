# DR-05 Batch 8 Release Recovery and Acceptance Evidence

Date: 2026-08-20

This is acceptance mapping and focused contract evidence for DR-05 Batch 8. It does not re-implement checkout, catalog, or WordPress connector runtime. It does not claim the overall product is live-ready or public-beta ready.

## Scope of this pass

- Batch 7 merchant go-live checklist is implemented on **Website → Connect your website**.
- Batch 8 maps the architecture’s required recovery scenarios onto existing Batches 1–6 tests plus new focused cutover/DR-06 contracts.
- The ten Batch 1–6 real-browser WordPress + Stripe scenarios were completed by the merchant on 2026-08-20. They were not re-implemented or re-run as code in this pass.
- Full `php artisan test` was **not** requested and was **not** run in this pass.
- Phase 9, extra carriers, and billing expansion remain out of scope.

## Commands executed

```text
php artisan test --filter="MerchantCutoverTest|Dr05Batch8ReleaseAcceptanceTest|Dr06MerchantAcceptanceTest|MerchantWebsiteConnectTest"
```

First run: `Dr06MerchantAcceptanceTest` failed twice (delivery setup index redirects; draft orders store customer email on the customer record). Those fixtures were corrected. Unrelated suites were not run.

Final focused result:

- **20 passed**
- **0 failed**
- **157 assertions**
- **3.53 seconds**

Classes included: `MerchantCutoverTest` (4), `Dr05Batch8ReleaseAcceptanceTest` (2), `Dr06MerchantAcceptanceTest` (3), `MerchantWebsiteConnectTest` (11).

## Batch 8 required-scenario mapping

| Required scenario | Coverage used | Status this pass |
|---|---|---|
| Clean WordPress without WooCommerce | `WordPressConnectorHardeningTest`; connector conflict reporter never auto-deactivates plugins | Mapped to existing tests; not re-run here |
| Existing WooCommerce during migration | `WooCommerceCatalogImportTest`; Website checklist import gates | Mapped; Batch 7 surfaces import failures as blocking |
| WooCommerce deactivated after validated migration | Merchant acknowledgement + rollback copy; portal never deactivates plugins | Implemented as confirmation, not remote plugin control |
| Conflicting WordPress Stripe/payment plugin | `WordPressConnectorHardeningTest`; health `production_ready` / conflicts gate | Mapped |
| Simple and variable products | Historical catalog/import tests; DR-06 product-create GET | Mapped; variant-create UI remains a broader DR-06 item |
| Guest checkout | Platform checkout tests from Batches 1–6; merchant browser scenarios 1–9 | Mapped + merchant-completed browser gate |
| Registered customer flow | Out of scope until storefront customer authentication is in scope | Disclosed; not claimed |
| Billing and shipping addresses | Existing checkout and draft-order tests; DR-06 draft-order create | Mapped |
| Tax and discounts | Existing tax/coupon tests; tax-off acknowledgement cannot skip Stripe/import gates | Mapped |
| Multiple eligible shipping options | Delivery setup + `DeliverySetupStatusService` readiness used by cutover | Mapped |
| Stripe success / failure / delayed retrieval / webhook recovery | `PlatformCheckoutHardeningTest`, `CheckoutPaymentInvariantTest`, `CheckoutIdempotencyClaimTest`; merchant browser scenarios 1–6 | Mapped + merchant-completed browser gate |
| Abandoned checkout and reservation expiry | Existing checkout expiry coverage (`checkouts:expire-abandoned`) | Mapped; not re-run here |
| Duplicate submit and idempotent retry | `CheckoutIdempotencyClaimTest`, `WordPressCheckoutAttemptTest`; browser scenario 7 | Mapped + merchant-completed browser gate |
| Stock changing during checkout / last unit | `PlatformCheckoutHardeningTest::test_insufficient_stock_and_last_unit_cannot_be_sold_twice` | Mapped; not re-run here |
| Connector rotation and revocation | `ConnectedSiteAuthTest`, `MerchantWebsiteConnectTest` | `MerchantWebsiteConnectTest` re-run this pass |
| SaaS temporary outage | WordPress processing/poll recovery from Batches 1–6 | Mapped; live outage drill not repeated |
| WordPress event endpoint outage | `CatalogCacheInvalidationTest` retry/reconciliation | Mapped; not re-run here |
| Product unpublishing and cache invalidation | `CatalogCacheInvalidationTest` | Mapped; not re-run here |
| Order confirmation and tracking | Platform checkout confirmation APIs; WordPress order-status page | Mapped |
| Cross-store security | `ConnectedSiteAuthTest`, `MerchantCutoverTest`, `Dr06MerchantAcceptanceTest` | Re-run cutover + DR-06 this pass |
| Historical legacy records remain readable | Retired write paths 404; `CheckoutMode::EXTERNAL` is historical only | Re-run `Dr05Batch8ReleaseAcceptanceTest` this pass |

## New focused tests

- `tests/Feature/MerchantCutoverTest.php` — Website checklist, Stripe/ack cannot fake activation, owner-only ack/activate, two-store isolation, rollback does not delete orders.
- `tests/Feature/Dr05Batch8ReleaseAcceptanceTest.php` — retired external write paths remain 404; platform checkout remains the only runtime mode; Website copy never claims plugin deletion.
- `tests/Feature/Dr06MerchantAcceptanceTest.php` — password recovery page, owner/manager/staff boundaries, two-store 404s, draft order stays on the current store.

## Disclosures

- No new browser-test suite was added for Batch 8.
- Stripe PaymentIntent IDs from the merchant’s ten-scenario run were not committed, by request.
- Full PHPUnit, frontend production build, and `migrate:fresh --seed` were not executed in this pass.
- The product is **not** live-ready / public-beta ready. Remaining P0 includes finishing DR-06 human acceptance, DR-07 customer identity editing, and a current full-suite green run.

## DR-05 verdict

DR-05 (merchant WordPress website connection, Batches 1–8) is **complete** for this workstream after:

1. merchant confirmation that the ten Batch 1–6 browser scenarios passed;
2. Batch 7 go-live checklist runtime;
3. this Batch 8 mapping and focused contract evidence.
