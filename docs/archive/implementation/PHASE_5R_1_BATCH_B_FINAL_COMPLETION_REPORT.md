# Phase 5R-1 Batch B Final Completion Report

## Summary

Phase 5R-1 Batch B completes the tax foundation work that remained after Batch A. Product taxability now has a store-scoped default resolver, product editing includes a platform-tax flag, draft/manual orders support manual and calculated tax modes, calculated draft tax is snapshotted into final orders, and external checkout tax totals remain externally owned.

Batch A baseline from the prior local report was `900 passed, 2 skipped, 4343 assertions`. Batch B focused on product defaults, draft tax, external preservation, and final regression.

## Files Created

- `app/Services/Catalog/ProductTaxableDefaultResolver.php`
- `app/Services/Draft/DraftTaxService.php`
- `app/Models/DraftTaxLine.php`
- `database/migrations/2026_06_25_010000_add_tax_amount_to_draft_order_items_table.php`
- `database/migrations/2026_06_25_010100_create_draft_tax_lines_table.php`
- `tests/Feature/ProductTaxableFlagTest.php`
- `tests/Feature/DraftTaxTest.php`
- `docs/implementation/PHASE_5R_1_BATCH_B_FINAL_COMPLETION_REPORT.md`

## Files Modified

- `app/Contracts/Payments/PaymentProviderInterface.php`
- `app/Http/Controllers/DraftOrderController.php`
- `app/Http/Controllers/OnboardingController.php`
- `app/Models/DraftOrder.php`
- `app/Models/DraftOrderItem.php`
- `app/Models/Store.php`
- `app/Models/TaxRate.php`
- `app/Services/Catalog/ProductImportProcessor.php`
- `app/Services/Catalog/ProductImportVariantFinalizer.php`
- `app/Services/DraftOrderService.php`
- `app/Services/ManualOrderConversionService.php`
- `app/Services/Payments/StripePlatformPaymentProvider.php`
- `app/Services/Shipping/CheckoutShippingService.php`
- `app/Support/ProductEditPayload.php`
- `database/seeders/CustomerAndOrderSeeder.php`
- `resources/views/user_view/draft_order_show.blade.php`
- `resources/views/user_view/partials/product_edit_modal.blade.php`
- `resources/views/user_view/product_workspace.blade.php`
- `routes/web.php`
- `tests/Feature/CheckoutPaymentInvariantTest.php`
- `tests/Feature/EnterpriseQaOriginRoutingHardeningTest.php`
- `tests/Feature/Phase5ExternalCheckoutSyncTest.php`
- `tests/Feature/Phase5RTaxMigrationRoundTripTest.php`
- `tests/Feature/Phase6CheckoutDeliveryMethodsTest.php`
- `tests/Feature/Phase6NearestEligibleOriginRoutingTest.php`
- `tests/Feature/PlatformCheckoutShippingTaxRecalculationTest.php`

## Product Taxable Default Resolver

`ProductTaxableDefaultResolver::forStore(Store $store)` reads the store's existing `TaxSetting::default_product_taxable`. It falls back to `true` only for legacy stores missing a tax setting and does not create settings on read.

The resolver is used in production product creation paths:

- onboarding product creation
- normal catalog creation
- quick product creation
- simple product import creation
- variant import product creation
- production-like seeder fallback products

Existing products are preserved. Normal product updates do not overwrite `is_taxable` unless the store owner explicitly submits that field.

## Product Taxable UI

The product edit modal now includes `Charge tax on this product` with deterministic hidden-checkbox behavior. The helper copy explains that the setting controls configured platform checkout tax and does not control external checkout tax.

Users with `catalog.manage` can change the value. View-only users can see product tax status but cannot mutate it. Cross-store product updates remain blocked.

## Draft Tax Schema

Added:

- `draft_order_items.tax_amount`
- `draft_tax_lines`

`DraftTaxLine` stores store-scoped tax snapshots with nullable `tax_rate_id`, jurisdiction fields, rate percent, taxable amount, tax amount, `applies_to`, settings version, and calculation timestamp.

Relationships were added on `DraftOrder`, `Store`, and `TaxRate`.

## Manual Draft Tax Behavior

Missing `metadata.tax_source` means `manual`.

Manual mode preserves the store owner's entered header tax amount. Draft show/GET does not calculate tax. Normal draft save does not auto-run the calculator. Manual conversion keeps the header tax behavior and does not create order tax lines.

If a calculated draft is manually edited in a tax-sensitive way, the draft switches back to manual mode, clears stale draft tax lines, and resets calculated item tax amounts to zero.

## Calculated Draft Tax Behavior

`POST /draft-orders/{draftOrder}/calculate-tax` explicitly calculates tax from store settings. It requires a usable shipping country and uses the existing `TaxCalculator`; no second tax formula was introduced.

Calculated mode persists:

- `draft_order_items.tax_amount`
- draft header `tax_total`
- recalculated draft total/grand total
- `draft_tax_lines`
- `metadata.tax_snapshot`
- `metadata.tax_source = calculated`

Exclusive prices add item and shipping tax to the total. Inclusive prices extract item tax and add shipping tax separately. Zero-percent lines are preserved.

## Draft-To-Order Tax Copying

`ManualOrderConversionService` does not run tax calculation during conversion.

For calculated drafts, conversion copies:

- item tax amounts to `order_items.tax_amount`
- `draft_tax_lines` to `order_tax_lines`
- draft tax snapshot metadata
- authoritative draft totals
- shipping tax breakdown

Later tax setting changes do not mutate existing orders.

## External Checkout Preservation

External checkout remains externally calculated. External sync does not route supplied totals through `CheckoutTotalsService`, `TaxCalculator`, store tax rates, or `product.is_taxable`.

Tests prove supplied external subtotal, shipping, discount, tax, and grand total are preserved when store tax is disabled, enabled, inclusive, or when products are non-taxable. No local order tax lines are generated unless the external contract later supports a tax-line snapshot.

## Batch A Hardening Carried Forward

Batch B also completed the preflight hardening requested for Batch A:

- local PaymentIntent currency mismatch blocks conversion without order, capture, or inventory deduction;
- checkout grand-total tampering blocks conversion without order, capture, or inventory deduction;
- unpaid Stripe PaymentIntents are updated in place during shipping amount recalculation with explicit mutable/blocked/terminal status policy and provider response validation;
- succeeded/processing/capture-stage intents reject shipping mutation instead of being superseded.

## Verification Results

Targeted results before final full-suite rerun:

- `php artisan test --filter=ProductTaxableFlagTest` - 8 passed, 41 assertions
- `php artisan test --filter=DraftTax` - 7 passed, 70 assertions
- `php artisan test --filter=Phase4DraftOrderTest` - 13 passed, 102 assertions
- `php artisan test --filter=ManualOrderConversion` - no tests found by that filter; behavior is covered by `DraftTaxTest` and `Phase4DraftOrderTest`
- `php artisan test --filter=Phase5ExternalCheckoutSyncTest` - 9 passed, 72 assertions
- `php artisan test --filter=PlatformCheckoutTaxTest` - 24 passed, 169 assertions
- `php artisan test --filter=CheckoutPaymentInvariantTest` - 9 passed, 61 assertions
- `php artisan test --filter=Tax` - 171 passed, 837 assertions
- `php artisan test --filter=ProductImport` - 67 passed, 301 assertions
- `php artisan test --filter=PlatformCheckoutShippingTaxRecalculationTest` - 18 passed, 178 assertions
- `php artisan test --filter=Phase6CheckoutDeliveryMethodsTest` - 6 passed, 45 assertions
- `php artisan test --filter=Phase6NearestEligibleOriginRoutingTest` - 5 passed, 41 assertions
- `php artisan test --filter=EnterpriseQaOriginRoutingHardeningTest` - 9 passed, 63 assertions
- `php artisan test --filter=Phase5RTaxMigrationRoundTripTest` - 1 passed, 18 assertions

Final results after the last code edit:

- `git diff --check` - passed
- `composer validate --strict --no-check-publish` - passed
- `composer dump-autoload` - passed
- `php artisan optimize:clear` - passed
- `php artisan migrate:fresh --seed` - passed
- `php artisan migrate:rollback --step=2` - passed for the two Batch B draft tax migrations
- `php artisan migrate` - passed after rollback
- `php artisan test --filter=CurrencyPrecision` - 20 passed, 45 assertions
- `php artisan test --filter=Phase5PlatformCheckoutStripeTest` - 9 passed, 72 assertions
- `php artisan test --filter=Inventory` - 28 passed, 153 assertions
- `php artisan test --filter=Phase6CheckoutDeliveryMethodsTest` - 6 passed, 45 assertions
- `php artisan test` - 932 passed, 2 skipped, 4618 assertions
- `vendor/bin/pint --test` - failed only on known unrelated carrier-formatting files:
  - `database/migrations/2026_06_05_010000_extend_carrier_api_events_for_fedex_validation_evidence.php`
  - `database/migrations/2026_06_05_010100_extend_fedex_validation_artifacts_for_evidence.php`
  - `tests/Feature/Phase6FedExValidationWorkspaceTest.php`
- `vendor/bin/pint --test` on the 29 Batch B touched PHP files - passed
- `php artisan project:source-archive --dry-run` - passed; 681 included files, with secrets, runtime storage, vendor, node_modules, sqlite databases, local archive outputs, and generated carrier validation evidence excluded

## PaymentIntent Status And Provider Response Hardening (2026-06-25)

Final closeout after update-in-place shipping recalculation:

### Mutable statuses

- `requires_payment_method`
- `requires_confirmation`

### Blocked statuses

- `requires_action`
- `processing`
- `requires_capture`
- `succeeded`
- unknown/unrecognized statuses

### Terminal statuses

- `canceled`
- `failed`
- `superseded`

Terminal/in-progress intents do not silently create replacement PaymentIntents.

### Provider validation

`PaymentIntentUpdateResult` carries actual Stripe response values. `CheckoutShippingService` validates provider intent ID, amount minor, currency, mutable status, and client-secret consistency before persisting local PaymentIntent changes. Mismatch throws `CheckoutPaymentSynchronizationException` and rolls back shipping/tax mutations.

## Verification Results

Final verification after PaymentIntent status-policy and provider-response hardening:

| Command | Result |
| --- | --- |
| `php artisan test --filter=PlatformCheckoutShippingTaxRecalculationTest` | 18 passed, 178 assertions |
| `php artisan test --filter=StripePlatformPaymentProviderTest` | 2 passed, 9 assertions |
| `php artisan test --filter=Phase6CheckoutDeliveryMethodsTest` | 6 passed, 44 assertions |
| `php artisan test --filter=CheckoutPaymentInvariantTest` | 9 passed, 61 assertions |
| `php artisan test --filter=Phase5PlatformCheckoutStripeTest` | 9 passed, 72 assertions |
| `php artisan test --filter=Tax` | 182 passed, 944 assertions |
| `php artisan test` | 932 passed, 2 skipped, 4618 assertions |
| `vendor/bin/pint --test` on touched PaymentIntent hardening PHP files | Passed, 10 files |

Historical Batch B preflight verification (superseded counts — retained for audit trail only):

| Command | Result |
| --- | --- |
| `php artisan test` before final hardening closeout | 919 passed, 2 skipped, 4515 assertions |

Deferred intentionally:

- coupons and discount rules
- tax provider APIs
- VAT registration and filing
- tax exemptions and customer tax IDs
- compound taxes
- postal tax lookup
- carrier tax
- returns/refunds tax adjustment
- B2B/Markets tax rules
- per-row spreadsheet taxable mapping

## Phase Status

Phase 5R-1 is complete. The next phase is:

`Phase 5R-2 - Coupons`

Carrier architecture and admin functionality were not changed. Model A remains the primary carrier architecture. No commit was made.
