# Handoff: Product & Stock / Inventory UX Enhancement

**Owner for this work:** [Colleague name]  
**Requested by:** Saad  
**Goal:** Finish merchant-facing product + stock inventory usability so development can close this residue quickly.  
**Out of scope for this handoff:** Carriers, SaaS subscriptions/billing, payment gateway work, Phase 9 API keys/webhooks (separate track).

---

## 1. Why this work exists

Merchants were confused while editing products:

- They changed **Stock Alert** thinking it was inventory quantity.
- Real sellable stock lives on **variants** (`product_variants.stock`), not as a simple product column.
- Stock field was buried in the Inventory section (below option groups), so saves often left stock at `0`.
- External Cuba storefront clone reads stock from SaaS APIs — if SaaS stock is wrong/0, the shop shows out of stock.

This is **merchant UX + save-path correctness**, not a new inventory engine. Multi-location inventory / stock movements already exist (Phase 3).

---

## 2. Already done (do not redo)

Recent fixes already landed in SaaS:

| Change | Where |
|--------|--------|
| Visible **Stock** field next to Base Price / SKU | `resources/views/user_view/partials/product_edit_modal.blade.php` |
| Renamed **Stock Alert** → **Low stock alert** + helper copy | same file |
| Top Stock field syncs into simple-product variant row on input/submit | JS in same Blade |
| Multi-variant products hide top Stock and show “set stock per variant” hint | same file |
| Fixed falsy JS bug (`stock \|\| ''` treated `0` as empty) | same file |
| Update save path no longer hardcodes default stock fallback to `0` blindly | `app/Http/Controllers/Store/OnboardingController.php` (`updateProductFromManagement`) |
| `meta.default_stock` updated from sum of variant stocks on save | same controller |

**Verify before starting:** open Edit Product on a simple product — you should see **Stock** and **Low stock alert** as separate fields.

---

## 3. Remaining work (assign this)

### A. Product edit consistency (high priority)

Make stock editing equally clear in **all** product edit surfaces, not only the modal:

1. **Product workspace edit page**  
   - `resources/views/user_view/product_workspace_edit.blade.php`  
   - Likely reuses `product_edit_modal` partial — confirm Stock UI appears and saves correctly on workspace save.
2. **Quick-add / create flows**  
   - Ensure create path seeds stock from the Stock field into the first variant row.
3. **Products list inventory column**  
   - Confirm list “Inventory” matches sum of variant stocks / location available after edit (no stale cache).

### B. Stock save correctness (high priority)

1. Confirm saving Stock = N updates:
   - `product_variants.stock`
   - inventory levels via existing `InventorySyncService` / stock movement recording (do **not** bypass movements)
2. Re-open product after save — Stock field must show N (including `0`).
3. Multi-variant products:
   - Top Stock hidden
   - Each variant row stock editable
   - Total stock summary correct
4. Avoid recreating variants unnecessarily when only stock/name/price change (today some paths delete+recreate variants → new IDs; prefer in-place update when structure unchanged).

### C. Merchant copy & guidance (medium)

1. Keep merchant language (no “payload”, “variant pivot”, etc. in main UI).
2. Prefer wording like:
   - Stock = units available to sell  
   - Low stock alert = warn when at/below this number  
3. Optional: short next-step hint after save (“Inventory updated — X units available”).

### D. Regression tests (required for “dev complete”)

Add/extend Feature tests covering:

1. Simple product edit: set stock to 25 → persist on variant → reload shows 25.  
2. Stock `0` saves and displays as `0` (not blank).  
3. Low stock alert change does **not** change sellable stock.  
4. Multi-variant: per-row stock persists.  
5. Store scoping: cannot edit another store’s product stock.  
6. Stock movement / inventory level updated when stock changes (use existing inventory architecture).

Suggested places to look for patterns:

- `tests/Feature/` product / inventory related tests  
- `OnboardingController::updateProductFromManagement`  
- `InventorySyncService`, `StockMovementRecorder`

### E. Optional polish if time allows

1. Products list filters: Low stock / Out of stock accuracy after edits.  
2. Product workspace inventory summary uses same source of truth as edit form.  
3. Prevent duplicate “ghost” variants when merchants save repeatedly without option-group changes.

---

## 4. Architecture rules (must follow)

From project canon (`AGENTS.md`, `.cursor/rules`):

1. **Store-scoped** — every read/write checks active store.  
2. **Stock is variant-level** — never invent a product-only stock column that bypasses variants.  
3. **Auditable inventory** — stock-affecting changes must go through stock movements / existing inventory services.  
4. **Do not** expand into carriers, billing, or Phase 9 webhooks in this task.  
5. Merchant UI must stay clear and trustworthy.

---

## 5. Key files

| File | Role |
|------|------|
| `resources/views/user_view/partials/product_edit_modal.blade.php` | Main edit UI + stock JS |
| `resources/views/user_view/product_workspace_edit.blade.php` | Workspace edit surface |
| `resources/views/user_view/products.blade.php` (or products list view) | List inventory display |
| `app/Http/Controllers/Store/OnboardingController.php` | Product create/update save pipeline (`updateProductFromManagement`) |
| `app/Http/Controllers/Catalog/ProductWorkspaceController.php` | Workspace payload including stock summaries |
| `app/Services/Inventory/*` | Inventory sync / levels / adjustments |
| `app/Models/ProductVariant.php` | Variant stock fields |

---

## 6. Acceptance criteria (definition of done)

This work is complete only when:

- [ ] Merchant can set **Stock** without confusing it with **Low stock alert**
- [ ] Simple product stock save persists and reloads correctly (including 0)
- [ ] Multi-variant stock edits persist per variant
- [ ] Products list / workspace inventory numbers match saved stock
- [ ] Inventory audit path still records stock-affecting changes
- [ ] Store isolation still holds
- [ ] Targeted Feature tests pass for the cases above
- [ ] No new carrier/billing/payment scope introduced

---

## 7. Suggested test plan (manual)

1. Login as Demo Merchant → store **Portal Check**.  
2. Edit a simple product (e.g. Huggies BW2XL): set Stock = `40`, Low stock alert = `5`, save.  
3. Re-open edit → Stock shows `40`, alert shows `5`.  
4. Products list inventory shows ~40.  
5. Set Stock = `0`, save → product shows out of stock / 0 inventory.  
6. Product with option groups: set different stocks per variant → totals correct.  
7. (If Cuba clone is running) `php artisan saas:sync-catalog --replace-local` in `Cuba-saas-connected` → storefront stock matches SaaS.

---

## 8. Timebox guidance

For “development complete ASAP”, prioritize:

1. Verify existing modal fix on workspace + create flows  
2. Save-path / inventory sync correctness  
3. Tests  
4. List/workspace display consistency  
5. Only then polish copy / duplicate-variant cleanup  

Estimate for a focused colleague who knows Laravel Blade: **1–2 days** for A–D; optional E after.

---

## 9. Contact / context

- Product vision: merchant-first, no technical jargon in main UI.  
- Canon docs: `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, `AGENTS.md`.  
- Related side project (not required for this handoff): `C:\Saad\Projects\Cuba-saas-connected` syncs catalog from SaaS APIs and depends on correct SaaS stock.
