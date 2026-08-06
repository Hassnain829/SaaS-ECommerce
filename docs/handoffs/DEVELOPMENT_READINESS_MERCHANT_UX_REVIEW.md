# Development Readiness & Merchant UX Review

**Date:** 2026-08-05  
**Scope:** Merchant-facing development readiness, excluding carrier expansion, SaaS subscriptions/billing, and payment expansion.

## Honest conclusion

The platform has a substantial and well-tested commerce core, but it is **not yet ready to present to non-technical merchants as a finished product**.

Catalog, variants, product import, inventory, orders, customers, delivery setup, notifications, team access, and security activity are real. The main remaining problem is trust: several visible pages still contain demo data, false launch claims, dead links, read-only “future” controls, or unfinished flows. A non-technical user cannot distinguish the real platform from the mock surfaces.

The recent product and stock work is functionally strong. However, the products list still opens the shared edit modal while a dedicated product workspace editor also exists. Stock logic should not be redesigned again, but the duplicate editing path must be removed before sign-off.

## What is already convincing

- Product catalog management, variants, import, stock movements, bulk actions, and inline price/stock editing.
- Store-scoped orders, draft orders, order detail, customers, addresses, notes, tags, and consent.
- Dashboard setup checklist and real operational summaries.
- Delivery setup for manual delivery areas/options.
- Team membership, role-aware actions, notifications, sessions, and security activity.
- Multi-store selection and store-scoped data access.

## Release blockers found

### 1. Visible fake or static business data

The merchant Analytics page shows hard-coded CAC, LTV, churn, cohorts, sales, and activity. Its controller supplies no store data. The platform-admin controllers also return static Blade templates containing invented revenue, uptime, tenant, user, and product metrics.

**Risk:** A merchant or operator will assume invented numbers are real.

### 2. Onboarding makes claims the product cannot prove

The final onboarding page says the marketplace is live, ready for customers, and available at `<store>.baas.com`, even though this repository does not provision that storefront/domain. It also contains encoding residue, a CDN Tailwind dependency, and malformed/dead “quick tour” markup.

**Risk:** The first-run experience creates false confidence at the most important trust moment.

### 3. Authentication recovery and legal links are incomplete

“Forgot password?”, Privacy Policy, and Terms links point to `#`. Email verification can be “pending” without a visible completion flow. Logout is a state-changing GET request.

**Risk:** Users can become permanently locked out, and public account screens look unfinished.

### 4. Unfinished scope remains in normal navigation and setup readiness

Billing and Payments are normal sidebar destinations. Payments are also part of the dashboard’s setup-completion calculation. General settings displays controls described as “editable later,” while Security says two-step verification is not configured.

**Risk:** Excluded roadmap scope prevents the current product from ever feeling complete.

### 5. The full automated suite is not green

Run on 2026-08-05:

- 1,542 passed
- 11 failed
- 2 skipped
- 8,655 assertions

Failures include stale copy/contract expectations and visibility regressions across the root response, managed channel mode, catalog wording, external/platform checkout visibility, FedEx views, and Stripe views.

**Risk:** The repository cannot provide a clean release signal even though most tests pass.

### 6. External selling channels are still developer-prototype quality

The visible surface is explicitly a “Developer test storefront” using one broad bearer token. It does not yet provide merchant-friendly connected-site setup, scoped credentials, webhook delivery status, idempotency management, or connection health.

**Risk:** The core product promise—central commerce operations for external websites—is not production-ready.

### 7. Customer identity is not fully manageable

Merchants can manage customer addresses, status, tags, notes, and marketing consent, but cannot directly correct the customer’s name, email, or phone from the customer profile.

**Risk:** Real customer service work reaches a dead end.

### 8. Core product entry points still contain bridge UX

The product list opens a modal editor even though the direct product workspace is the intended core editing surface. A genuinely empty catalog also shows filter-style “no results” guidance instead of clear Add product and Import product actions. The onboarding product step contains an inert “Upload CSV” button and remains a second legacy product/variant editor.

**Risk:** Merchants see two product-editing experiences, encounter a dead import action, and receive weak guidance at the most important catalog empty state.

## Prioritized development tasks

### P0 — Finish before non-technical acceptance

#### DR-01 — Product truth and feature-gating pass

- Remove Analytics from merchant navigation until it uses real store-scoped data, or replace it with a small real operational report.
- Block or hide static platform-admin pages until each page is backed by real queries and real actions.
- Hide SaaS Billing and payment-expansion surfaces from the default merchant journey while those areas are excluded.
- Keep manual Delivery visible, but demote unfinished carrier connections in Delivery overview and place them behind an explicit advanced/preview gate.
- Remove all unsupported performance, uptime, scale, domain, and “production-ready” claims from sign-in/register/onboarding.
- Align sign-in/register branding with the merchant product; stop presenting “BaaS Core / Infrastructure” as the live product identity.

**Acceptance:** Every visible number comes from the active store/platform database; every visible feature works; preview areas are unmistakably labeled and do not block setup.

#### DR-01A — Make the product workspace the only primary editor

- Route Edit from the products list directly to `products.edit`.
- Retire the modal as the primary catalog editing path.
- Keep inline price/stock actions for quick changes, with the workspace as the full editor.
- Add a true empty-catalog state with primary Add product and Import products actions.
- Promote Import beside Add product as a primary catalog action, not only under More.
- Replace internal wording such as “soft-deleted” with “Deleted products” and “Undo delete.”
- Finish removing the legacy onboarding Step 2 product editor from day-to-day add flows so Store management and create both land in the workspace path.

**Acceptance:** Merchants have one predictable full editing surface; empty catalogs explain exactly how to add or import the first products.

#### DR-02 — Rebuild onboarding completion around truthful readiness

- Replace “your marketplace is live” with “your management workspace is ready.”
- Do not show a storefront URL unless a real connected channel/domain exists.
- Send merchants to a next-step checklist: add products, review inventory, connect a selling channel, configure delivery, invite a teammate.
- Remove the dead quick-tour control, encoding corruption, standalone Tailwind CDN, and malformed markup.
- Remove the inert onboarding “Upload CSV” control and link to the real import workflow instead.
- Stop maintaining onboarding Step 2 as a second long-term product editor; reuse or redirect to the product workspace.
- Make setup completion ignore excluded payment/subscription/carrier work.

**Acceptance:** A new merchant can complete registration and onboarding without seeing a false claim, dead action, or required excluded feature.

#### DR-03 — Complete account access and recovery

- Implement password reset request, email delivery, signed reset token, and reset form.
- Add email-verification send/resend/confirm flow, or remove “pending verification” until it exists.
- Replace dead Terms and Privacy links with real pages/configured URLs.
- Convert logout to POST with CSRF protection.
- Wire and test password visibility controls.

**Acceptance:** A user can register, verify, sign in, recover access, change password, and sign out without developer help.

#### DR-04 — Establish a clean release test gate

- Triage all 11 current failures as product defects or stale test expectations.
- Fix the implementation when current behavior is wrong; update tests only when the new behavior is explicitly accepted.
- Add tests for onboarding truth, auth recovery, hidden feature gates, and absence of static analytics/admin data.
- Require `migrate:fresh --seed` and the full test suite in CI.

**Acceptance:** Fresh install succeeds and the full suite passes with zero failures.

#### DR-05 — Production connected-channel foundation

- Replace the single developer token with store-scoped API credentials and explicit scopes.
- Add idempotency keys for external order/customer writes.
- Add signed outbound webhooks with retry history and delivery diagnostics.
- Add an event outbox so external calls are asynchronous and auditable.
- Build a merchant “Connected sites” hub with connection name, status, last sync, credential rotation, and setup instructions.
- Harden the Cuba proof integration against stale catalog data and duplicate product/variant mappings.

**Acceptance:** A merchant can connect a site without editing `.env`, rotate access safely, see connection health, and retry failed deliveries without duplicate orders.

#### DR-06 — Run end-to-end merchant acceptance

Test with owner, manager, and staff accounts across two stores:

1. Register and recover account.
2. Create/select store.
3. Add simple and variant products.
4. Import a mixed-quality catalog.
5. Adjust stock and verify stock movement history.
6. Connect an external test site and receive an order.
7. Create and convert a manual draft order.
8. Update order/customer data.
9. Configure a manual delivery area and option.
10. Invite a teammate and verify restricted actions.

**Acceptance:** No cross-store leakage, dead controls, unexplained errors, false success messages, or developer-only wording in the normal merchant path.

#### DR-08 — Make General Settings actionable

- Provide one clear edit action for store name, contact email, logo, address, currency, timezone, and supported business defaults.
- Remove “editable later” cards from the primary interface.
- Explain which changes affect future orders versus historical records.
- If full in-page editing is not ready yet, deep-link to a working edit surface with an unmistakable CTA instead of leaving Settings as inspection-only.

**Acceptance:** Every setting shown as configuration can be changed by an authorized user, or is displayed as a clearly labeled read-only fact.

### P1 — Complete immediately after blockers

#### DR-07 — Finish customer profile editing

- Add a direct Add customer action, or explicitly guide merchants to create a customer from a manual order.
- Add store-scoped editing for name, email, and phone.
- Define duplicate-email behavior per store.
- Record identity changes in the security/activity log.
- Add clear validation and success feedback.

**Acceptance:** Authorized merchants can correct customer identity without recreating the customer.

#### DR-09 — Build real operational analytics or keep it hidden

- Start with merchant-useful data already available: sales, orders, average order value, product performance, low stock, and customer growth.
- Add real date filters and CSV export before offering PDF export.
- Include zero-data explanations and links to the action that creates data.

**Acceptance:** Every report is store-scoped, reproducible from source records, and contains no seeded/demo values.

#### DR-10 — Replace static platform admin with a real operations minimum

- Real store/user/product/order counts.
- Store lookup and support-safe store inspection.
- Queue, failed job, webhook, import, and notification-delivery health.
- Security/audit lookup and controlled support actions.

**Acceptance:** Platform operators can diagnose a merchant issue without querying the database manually; no admin page displays invented data.

#### DR-11 — Security completion

- Add two-step verification for owner/admin accounts.
- Add recovery codes and re-authentication for destructive/sensitive actions.
- Review whether managers should be allowed to invite users or modify roles.
- Add session/device confirmation for high-risk changes.

**Acceptance:** Sensitive account and store changes require appropriate permissions and recent authentication.

#### DR-12 — Navigation, copy, and accessibility consistency pass

- Standardize the product name; remove mixed “BaaS Core,” “BaaS Platform,” and “Merchant admin” identity.
- Soften notifications lead copy away from logistics-heavy framing; hide Mobile Push until a real delivery path exists.
- Keep Test storefront honest, but put a short merchant setup path above the API dump.
- Standardize headings, capitalization, button wording, status labels, currency formatting, and empty states.
- Verify keyboard use, focus, labels, confirmation dialogs, mobile layouts, and loading/saving states.
- Remove remaining dead links and no-op buttons.

**Acceptance:** A non-technical user can predict what every action does and always knows whether it succeeded.

### P2 — Development maturity after merchant sign-off

#### DR-13 — Observability

- Structured logs with store, user, request, job, integration, and correlation identifiers.
- Metrics and alerts for queue lag, failed jobs, import failures, webhook failures, API error rate, and slow requests.
- Operator runbooks for common failures.

#### DR-14 — Fine-grained permission customization

- Permission presets beyond Owner/Manager/Staff.
- Per-domain access for catalog, inventory, orders, customers, settings, and integrations.
- Permission-change audit history.

#### DR-15 — Deferred roadmap

Keep these out of the development-readiness gate requested for this review:

- Additional carriers and production carrier approvals.
- SaaS plans, subscriptions, invoices, and billing.
- Payment provider expansion and advanced payment operations.
- Markets/B2B, advanced returns/exchanges, and other later roadmap expansion.

## Recommended execution order

1. DR-01 Product truth and feature gating.
2. DR-01A Single product workspace path.
3. DR-08 Actionable General Settings.
4. DR-02 Onboarding completion.
5. DR-03 Account recovery and legal routes.
6. DR-04 Full test suite green.
7. DR-05 Connected-channel foundation.
8. DR-06 Merchant acceptance.
9. DR-07 and DR-09 through DR-12.
10. DR-13 and DR-14 after merchant sign-off.

## Sign-off standard

Do not call the product development-ready because a page exists or a test passes. Sign off only when:

- all visible data is real;
- every visible control works;
- a merchant can recover from mistakes;
- the active store boundary is preserved;
- excluded features do not block or confuse the core flow;
- the full suite is green;
- an owner, manager, and staff user complete the acceptance journey without developer assistance.
