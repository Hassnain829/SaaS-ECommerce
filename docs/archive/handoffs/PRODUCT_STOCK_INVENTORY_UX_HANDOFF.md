# Handoff: Products List UX + Stock / Inventory Enhancement

**Owner for this work:** [Colleague name]  
**Requested by:** Saad  
**Goal:** Finish merchant-facing products list UX (search/filters/soft delete/bulk) and product + stock inventory usability.  
**Out of scope for this handoff:** Carriers, SaaS subscriptions/billing, payment gateway work, Phase 9 API keys/webhooks (separate track).  
**Last updated:** 2026-08-05  
**Git status (as of update):** These list/stock changes are largely in the **local working tree** (not yet a dedicated commit). Tracked against `main` via uncommitted diffs + new files listed in §10.

---

## 1. Why this work exists

Merchants were blocked by several products-page problems at once:

- Hard to find products (search/filters scattered or weak).
- Delete felt permanent with no undo / trash view.
- Bulk actions were technical and capped at 500 IDs.
- They confused **Stock Alert** with sellable inventory quantity.
- Real sellable stock lives on **variants** (`product_variants.stock`).
- After inline list edits, opening Edit could show **stale** stock (updated once, then stuck).
- External Cuba storefront clone reads stock from SaaS APIs — wrong SaaS stock shows as out of stock on the shop.

This is **merchant UX + save-path correctness**, not a new inventory engine. Multi-location inventory / stock movements already exist (Phase 3).

---

## 2. Already done (do not redo)

### A. Products list UI — search, filters, layout (2026-08-05)

Unified catalog page in `resources/views/user_view/products.blade.php` + `DashboardController::product`:

| Change | Detail |
|--------|--------|
| Page intro | “Product catalog” / “Deleted products” heading, store badge, short merchant lead copy |
| View toggle | **Products** vs **Deleted** tabs (`?view=deleted`); deleted badge count |
| Stats strip | In view / Out of stock / Low stock / Brands (uses `ProductInventoryState` for low/out) |
| Unified **Search & filters** panel | `#products-filters-panel` — search moved out of merchant topbar into the panel |
| Search | `#products-filter-q` (`q` query) on name/SKU/etc. |
| Filters | Category, brand, tag, type, stock (low/out), sort, attribute term, custom-field key/value |
| Filter pickers | Searchable pickers (`data-filter-picker`, `data-picker-search`) instead of dense chip groups / raw selects |
| Active filter chips | Clearable brand/tag/category/etc. chips above the table |
| Reset | Clears filters while preserving active vs deleted view |
| Pagination | `per_page` 10/25/50/100; page links keep `q` and other query params |
| Low-stock filter | Requires `stock_alert > 0` (avoids false “low” when alert is 0) |

**Tests:** `tests/Feature/ProductsListUxTest.php`  
- `test_unified_filter_panel_includes_search_sort_and_hides_header_search`  
- `test_filter_apply_updates_listing`  
- `test_pagination_per_page_and_page_links_respect_query_string`

**Related:** `tests/Feature/MerchantTopbarTest.php` updated so products search is not expected in the topbar slot.

### B. Soft delete / Deleted products view (2026-08-05)

| Change | Detail |
|--------|--------|
| Soft delete on row Delete | `product.destroy` now soft-deletes (`$product->delete()`), not force-delete |
| Deleted catalog view | `?view=deleted` → `onlyTrashed()` store-scoped list |
| Restore | `POST product.restore` → `restoreProductFromManagement` |
| Permanent delete | `DELETE product.force-destroy` → `forceDestroyProductFromManagement` |
| Gallery files | Soft delete **keeps** image files; only force-delete clears disk (`Product` model `deleting` guard) |
| Merchant copy | Success messages point merchants to **Deleted products** to undo |
| Security logs | `product_soft_deleted`, `product_restored`, `product_force_deleted` |
| Store scoping | Cross-store restore / force-delete → 404 |
| Bulk on deleted view | `restore` + `force_delete` actions in `ProductBulkController` |

**Routes** (`routes/onboarding.php`):

- `product.destroy` (soft)
- `product.restore`
- `product.force-destroy`

**Tests:** `ProductsListUxTest` soft-delete / restore / force / gallery / cross-store / bulk restore+force cases.

### C. Bulk actions toolbar UX (2026-08-05)

| Change | Detail |
|--------|--------|
| Toolbar hidden until selection | `#bulk-catalog-toolbar` starts `hidden` |
| Merchant chips | Update stock, Publish/draft, Categories, Brand, Tags, Delete (not a technical-only dropdown) |
| Deleted view chips | Undo delete / Permanently delete |
| Options panel | Plain-language fields + Continue / Cancel |
| Confirm copy | Soft-delete explains undo from Deleted products |

### D. Bulk “Select all matching” — full filtered set (2026-08-05)

| Change | Where |
|--------|--------|
| Removed **500-ID cap** | `DashboardController` — `bulkMatchingCount` = full filtered set |
| Large selections via `product_ids_json` | Avoids PHP `max_input_vars` limits |
| Server safety cap ~**20,000** IDs | `ProductBulkController` |
| UI count = total matching in current filters | list Blade + tests |

**Tests:** `ProductBulkActionsTest`, `ProductsListUxTest::test_select_all_matching_uses_full_filtered_product_count`

### E. Edit popup / workspace Basics stock (earlier + 2026-08-05)

| Change | Where |
|--------|--------|
| Visible **Stock** next to Base Price / SKU | `product_edit_modal.blade.php` |
| **Stock Alert** → **Low stock alert** + helper copy | same |
| Top Stock syncs into simple-product variant row | modal JS |
| Multi-variant: hide top Stock; set per option | same |
| Fixed falsy `stock \|\| ''` treating `0` as empty | same |
| `renderVariantRows()` `skipDomSync` so Basics stock is not overwritten | same |
| Alert syncs onto single inventory row for simple products | same |
| Server maps `bulk_stock` + `stock_alert` onto single variant | `OnboardingController::updateProductFromManagement` |
| `meta.default_stock` from sum of variant stocks | same |
| Workspace edit shares Basics stock UX | `product_workspace_edit.blade.php` |

**Tests:** `tests/Feature/ProductEditBasicsStockTest.php`

### F. Products list — inline price / stock (2026-08-05)

| Change | Where |
|--------|--------|
| Inline price + stock editors | `products.blade.php` |
| Simple stock | `PATCH products.inline.stock` |
| Multi-option: total + “N options · edit” popover | `#inline-variant-stock-popover` |
| Batch option stocks | `PATCH products.inline.variant-stocks` |
| Enter key via **document event delegation** | list JS |
| Inventory movements preserved | `ProductInlineController` → adjustment services |

**Routes** (`routes/web.php`): `products.inline.price`, `.stock`, `.variant-stocks`, `.detach-category`

**Tests:** `tests/Feature/ProductInlineEditTest.php`

### G. Live list ↔ Edit popup sync (2026-08-05) — “stuck after first change”

**Problem:** Edit button `data-product` JSON stayed at page-load values. First inline save stuck forever on reopen.

**Fix — client live map until full page reload:**

| Piece | Role |
|-------|------|
| `window.__liveProductValuesById` | `{ price, stock, inventory, variants?, updatedAt }` |
| `window.__productEditPayloadById` | Patched full edit payloads |
| `rememberLiveValues` / `readLiveProductValues` | Live map R/W |
| `hydratePayloadFromListRow` | Merge live into payload when opening Edit |
| `syncEditPopupAfterInlineStock` | After **every** inline stock save |

- **Simple:** live stock re-applied every Edit open.  
- **Multi-option:** live `variants: [{id, stock}, …]`; popover + `openEdit` prefer live stocks every time.

**Rule:** never trust page-load `data-product` alone after an inline edit.

---

## 3. Remaining work (assign this)

### A. Consistency / polish (medium)

1. Quick-add / create flows — confirm create path seeds stock from the Stock field into the first variant row.
2. List filters (Low / Out) freshness immediately after inline edits without full reload.
3. Optional success hint after save (“Inventory updated — X units available”).

### B. Save-path hardening (medium)

1. Prefer in-place variant updates when option structure is unchanged (avoid delete+recreate → new IDs).
2. Prevent duplicate “ghost” variants on repeated saves without option-group changes.

### C. Merchant copy (low)

Keep merchant language (no “payload”, “variant pivot” in main UI).

### D. Sign-off

Run manual checklist in §7. Commit the working-tree bundle when ready (§10).

---

## 4. Architecture rules (must follow)

1. **Store-scoped** — every read/write checks active store.  
2. **Stock is variant-level** — never invent a product-only stock column that bypasses variants.  
3. **Auditable inventory** — stock-affecting changes go through stock movements / inventory services.  
4. **Soft delete first** — row/bulk Delete soft-archives; permanent delete is explicit and separate.  
5. **Do not** expand into carriers, billing, or Phase 9 webhooks in this task.  
6. **List/edit UI sync** — use `__liveProductValuesById` (including per-variant rows for multi-option products).

---

## 5. Key files

| File | Role |
|------|------|
| `resources/views/user_view/products.blade.php` | List UI, filters, soft-delete view, bulk bar, inline editors, live map |
| `resources/views/user_view/partials/product_edit_modal.blade.php` | Edit UI + Basics stock + live re-apply on open |
| `resources/views/user_view/product_workspace_edit.blade.php` | Workspace edit (reuses modal partial) |
| `resources/views/components/ui/merchant-topbar.blade.php` | Topbar no longer hosts products search |
| `app/Http/Controllers/Store/DashboardController.php` | List query, filters, `catalogView`, `bulkMatchingCount`, `deletedCount` |
| `app/Http/Controllers/Store/OnboardingController.php` | Soft delete / restore / force-destroy + product update stock mapping |
| `app/Http/Controllers/Catalog/ProductInlineController.php` | Inline price/stock + batch variant stocks |
| `app/Http/Controllers/Catalog/ProductBulkController.php` | Bulk actions + `product_ids_json` + restore/force_delete |
| `app/Models/Product.php` | SoftDeletes; gallery cleared only on force-delete |
| `app/Support/ProductInventoryState.php` | Shared low/out stock state for list stats |
| `routes/onboarding.php` | `product.destroy` / `restore` / `force-destroy` |
| `routes/web.php` | `products.inline.*`, `products.bulk` |
| `tests/Feature/ProductsListUxTest.php` | Filters, soft delete, bulk matching count |
| `tests/Feature/ProductInlineEditTest.php` | Inline stock/price + live-map markers |
| `tests/Feature/ProductEditBasicsStockTest.php` | Basics stock / alert save |
| `tests/Feature/ProductBulkActionsTest.php` | Bulk + large JSON ID sets |

---

## 6. Acceptance criteria

- [x] Unified Search & filters panel (search not in topbar)
- [x] Soft delete + Deleted view + restore + permanent delete
- [x] Soft delete keeps gallery files until force-delete
- [x] Bulk toolbar merchant chips; deleted-view undo/permanent actions
- [x] “Select all matching” = full filtered set (not capped at 500)
- [x] Merchant can set **Stock** without confusing it with **Low stock alert**
- [x] Simple + multi-variant stock save/reload correct (including 0)
- [x] Inline list stock/price updates Edit popup on **every** change
- [x] Inventory audit path still records stock-affecting inline changes
- [x] Store isolation holds
- [x] Targeted Feature tests for list UX / inline / basics stock
- [ ] Manual sign-off checklist (§7)
- [ ] Working-tree changes committed (§10)
- [ ] Optional polish in §3 closed or deferred
- [x] No new carrier/billing/payment scope

---

## 7. Suggested test plan (manual)

1. Login as Demo Merchant → store **Portal Check**.  
2. **Filters:** open Search & filters → search by name → apply brand/category/sort → Reset. Confirm topbar has no products search box.  
3. **Soft delete:** Delete a product → gone from Products → appears under Deleted → Undo delete → back on Products. Force-delete from Deleted → gone for good.  
4. **Bulk:** select rows → chip actions work; on Deleted view, restore / permanently delete work. Select all matching count = filtered total.  
5. **Basics stock:** Edit simple product Stock = `40`, Low stock alert = `5` → reopen shows both.  
6. **Inline simple:** change list stock twice → open Edit each time → shows latest (not stuck).  
7. **Inline multi-option:** options popover save twice → reopen popover + Edit → latest option stocks.  
8. Stock = `0` → out of stock / 0 inventory.  
9. (Optional) Cuba sync: `php artisan saas:sync-catalog --replace-local` → storefront matches SaaS.

---

## 8. Timebox guidance

1. Manual §7 (filters, soft delete, live-sync, bulk).  
2. Commit working-tree bundle (§10).  
3. Create-flow stock seed check if needed.  
4. Optional polish last.

Core products-list + stock UX for this handoff is **functionally complete** as of 2026-08-05; remaining items are sign-off, commit, and optional polish.

---

## 9. Contact / context

- Product vision: merchant-first, no technical jargon in main UI.  
- Canon docs: `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, `AGENTS.md`, `PROJECT_STRUCTURE.md`.  
- Related side project (not required): `C:\Saad\Projects\Cuba-saas-connected`.

---

## 10. Git tracking note (2026-08-05)

Compared to `main` / `origin/main`, this session’s products work lives mainly as **uncommitted local changes**, including:

**Modified (high signal):**

- `resources/views/user_view/products.blade.php` (large list UX rewrite)
- `resources/views/user_view/partials/product_edit_modal.blade.php`
- `resources/views/user_view/product_workspace_edit.blade.php`
- `resources/views/components/ui/merchant-topbar.blade.php`
- `app/Http/Controllers/Store/DashboardController.php`
- `app/Http/Controllers/Store/OnboardingController.php`
- `app/Http/Controllers/Catalog/ProductBulkController.php`
- `app/Models/Product.php`
- `routes/web.php`, `routes/onboarding.php`
- Related Feature test updates (`Phase2CatalogCleanupTest`, `ProductBulkActionsTest`, `MerchantTopbarTest`, …)
- Docs: this handoff, `docs/README.md`, `PROJECT_STRUCTURE.md`

**New (untracked):**

- `app/Http/Controllers/Catalog/ProductInlineController.php`
- `app/Support/ProductInventoryState.php`
- `tests/Feature/ProductsListUxTest.php`
- `tests/Feature/ProductInlineEditTest.php`
- `tests/Feature/ProductEditBasicsStockTest.php`

Earlier committed touchpoints nearby (not the full list rewrite):

- `a47bf4c` — initial stock UX handoff doc  
- `f932454` — smaller product management UI/backend stock-related fix  

When committing, prefer one coherent commit (or a short series) covering **list UX + soft delete + bulk matching + inline stock + live sync + docs**, so the products-page story stays reviewable together.
