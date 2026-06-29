# FedEx Integrator Validation — Packages 1–3 Session Report

**Date:** 24 May 2026  
**Scope:** FedEx Model A integrator validation workspace (sandbox account `700257037`)  
**Status:** Packages 1–3 implemented and verified; post-Package-2 hardening applied; ready to begin Package 4.

---

## Executive summary

This session completed the guided implementation packages for FedEx integrator validation evidence:

| Package | Focus | Status |
|---------|--------|--------|
| **1** | Evidence sanitizer — preserve `shippingChargesPayment`, redact secrets | Done |
| **2** | Parent + Child OAuth authorization (one-click, fresh network calls) | Done |
| **3** | Sweden MFA Passthrough (validation-only, no account mutation) | Done |
| **Hardening** | Secret scan, OAuth preflight strictness, export regression tests | Done |

Sweden MFA Passthrough was unblocked by correcting the workbook address format: street line must be `HAGAGATAN 1, VI` (comma included), not `VI` as a separate state field.

---

## Package 1 — Evidence sanitizer

### Problem

The sanitizer treated the substring `pin` inside `shippingChargesPayment` as sensitive, redacting the whole payment object. FedEx validation requires exported ship JSON to show:

```json
"shippingChargesPayment": {
  "paymentType": "SENDER"
}
```

### Solution

- Added shared classifier: `app/Services/Carriers/FedEx/Validation/FedExSensitiveFieldClassifier.php`
- Updated `FedExValidationEvidenceSanitizer.php` and `CarrierApiEventLogger.php` to use it
- `paymentType`, `token_type`, and structured payment objects are preserved
- Actual secrets (`access_token`, `client_secret`, `child_secret`, PIN fields, etc.) remain redacted

### Tests

- `tests/Unit/FedExSensitiveFieldClassifierTest.php`
- `tests/Unit/FedExValidationEvidenceSanitizerTest.php`
- Export-level regression: `Phase6FedExValidationCorrectionTest::test_diagnostic_export_preserves_shipping_charges_payment_object_in_ship_requests`

**Note:** Events recorded before this fix may still contain bad redaction in the DB. Re-run US02/US04/US05 label flows before final export if those events predate Package 1.

---

## Package 2 — Parent + Child OAuth authorization

### Goal

Separate, fresh OAuth evidence for FedEx certification — not cached tokens, not mixed with registration flows.

### Implementation

| Component | Purpose |
|-----------|---------|
| `FedExValidationAuthorizationEvidenceService.php` | Runs parent (`client_credentials`) then child (`csp_credentials`) with `fresh: true` |
| Scenario keys | `authorization_parent`, `authorization_child` |
| Export folders | `01_registration_mfa/01_parent_authorization/`, `02_child_authorization/` |
| Workspace UI | **Run Parent + Child Authorization** one-click button |
| Preflight | Required checks for both authorization scenarios |

### Behaviour

- Parent failure skips child run
- Cached OAuth results do not pass preflight
- Full account numbers and credential input fields are not shown in workspace
- Route: `POST .../fedex/validation/run/authorization` (authenticated, store-scoped)

### Tests

- `tests/Feature/Phase6FedExAuthorizationEvidenceTest.php` (11 tests)

---

## Package 3 — Sweden MFA Passthrough

### Goal

One-click validation for the locked Sweden workbook account (ending **9268**, account `604849268`). Registration must return child credentials directly — no PIN, SMS, email, call, or invoice MFA steps. Child OAuth runs ephemerally; **no** permanent account mutation.

### Critical rule

**Do not** call `FedExIntegratorRegistrationOrchestrator::completeRegistrationFromFedExResponse()` in this flow.

### Implementation

| Component | Purpose |
|-----------|---------|
| `FedExValidationSwedenPassthroughService.php` | Validation-only orchestrator |
| `FedExValidationSwedenPassthroughSupport.php` | Case key, failure message, export folder |
| Scenario keys | `registration_sweden_passthrough_address`, `authorization_sweden_passthrough_child` |
| Export folder | `01_registration_mfa/12_sweden_mfa_passthrough/` |
| Workspace UI | **Run Sweden MFA Passthrough** + screenshot upload (2 files) |
| Route | `POST .../fedex/validation/run/sweden-passthrough` |

### Sweden address fix (blocker resolved)

Workbook format: `(HAGAGATAN 1, VI, STOCKHOLM, 11349, SE)`

FedEx sandbox requires:

| Field | Correct value |
|-------|----------------|
| `streetLines[0]` | `HAGAGATAN 1, VI` |
| `city` | `STOCKHOLM` |
| `postalCode` | `11349` |
| `countryCode` | `SE` |
| `stateOrProvinceCode` | **omit** — `VI` is part of the street line, not a state code |

Wrong formats (all returned `ACCOUNT.ADDRESS.MISMATCH`):

- `HAGAGATAN 1` + `stateOrProvinceCode: VI`
- `HAGAGATAN 1` alone
- Full address crammed into one `.env` line

Correct `.env` fallback:

```env
FEDEX_VALIDATION_SWEDEN_ACCOUNT_NUMBER=604849268
FEDEX_VALIDATION_SWEDEN_CUSTOMER_NAME="Unique Customer Name"
FEDEX_VALIDATION_SWEDEN_ADDRESS_LINE1="HAGAGATAN 1, VI"
FEDEX_VALIDATION_SWEDEN_CITY=STOCKHOLM
FEDEX_VALIDATION_SWEDEN_POSTAL_CODE=11349
FEDEX_VALIDATION_SWEDEN_COUNTRY_CODE=SE
```

Do **not** add `registration_sweden_passthrough_address=` to `.env` — those are internal scenario keys, not environment variables.

Parser fix: `FedExTestCaseFixtureService::parseSwedenParentheticalAddress()` merges the second token into street line 1.

### Screenshots required (after successful run)

1. **Address/passthrough result** — Sweden card showing Passed / child credentials / MFA bypassed
2. **Direct child authorization** — same card with Direct child authorization: Passed

Do not upload Rate Quote, PIN, or generic checklist screenshots for Sweden.

### Tests

- `tests/Feature/Phase6FedExSwedenPassthroughTest.php` (11 tests)

---

## Post-Package-2 hardening (fixes.txt)

External review identified gaps after Package 2. All applicable fixes were implemented in this session.

### Fix 1 — Secret scanner bypass (critical)

**Why it matters:** Even in sandbox, the final ZIP is emailed to FedEx. The export pipeline runs `scanStagingDirectory()` before zipping. A bug allowed real secrets to pass if the same file also contained `[REDACTED]` placeholders.

**Fix:** Removed file-level `[REDACTED]` exemption; bearer scan strips sanitized placeholders before matching.

**File:** `FedExValidationEvidenceSanitizer.php`

### Fix 3 & 4 — OAuth preflight strictness

**New class:** `FedExValidationAuthorizationEvidenceRules.php`

Valid authorization evidence must include:

- **Parent request:** `grant_type`, redacted `client_id` / `client_secret`; no child fields
- **Child request:** above plus redacted `child_key` / `child_secret`
- **Response:** redacted `access_token`, `token_type=bearer`, numeric `expires_in`

`FedExValidationEvidenceQueryService::canonicalAuthorizationEvent()` now selects the latest event that fully satisfies these rules (valid older event preferred over invalid newer event).

### Fix 2, 5, 6 — Test coverage

- ZIP export verifies `shippingChargesPayment.paymentType` for US02/US04/US05
- Authorization test renamed to diagnostic export; added final export block test for incomplete OAuth
- Child OAuth response sanitization assertions added

### Deferred (low priority)

- `use_baseline=0` API edge case
- Unused `fedExQuickTestActions` view variable
- Quick-test “baseline” wording for service/rate (diagnostics only; certification uses validation workspace)

---

## Test summary

All FedEx validation-related tests passing at time of report:

```bash
php artisan test --filter="FedExValidation|Phase6FedExAuthorization|Phase6FedExSweden"
```

**78 tests, 233 assertions** (includes Packages 1–3 + hardening).

---

## Key file index

| Path | Role |
|------|------|
| `app/Services/Carriers/FedEx/Validation/FedExSensitiveFieldClassifier.php` | Sensitive field detection |
| `app/Services/Carriers/FedEx/Validation/FedExValidationEvidenceSanitizer.php` | Redaction + export secret scan |
| `app/Services/Carriers/FedEx/Validation/FedExValidationAuthorizationEvidenceService.php` | Package 2 OAuth runner |
| `app/Services/Carriers/FedEx/Validation/FedExValidationAuthorizationEvidenceRules.php` | OAuth evidence validation rules |
| `app/Services/Carriers/FedEx/Validation/FedExValidationSwedenPassthroughService.php` | Package 3 orchestrator |
| `app/Services/Carriers/FedEx/Validation/FedExTestCaseFixtureService.php` | Workbook + Sweden fixture parsing |
| `app/Services/Carriers/FedEx/Validation/FedExValidationPreflightService.php` | Readiness checks before final export |
| `app/Services/Carriers/FedEx/Validation/FedExValidationEvidenceExporter.php` | Diagnostic + final ZIP |
| `resources/views/user_view/fedex_validation/workspace.blade.php` | Validation workspace UI |
| `docs/fedex/FedEx_Integrator_Test_Case_Baseline.xlsx` | FedEx baseline workbook |
| `routes/carriers.php` | Validation run + upload routes |

---

## Workspace validation progress (merchant checklist)

Packages 1–3 code is complete. **Final FedEx submission** still requires remaining evidence on the validation workspace (~34 checks), including:

- Registration MFA steps (email PIN, phone PIN, address validation, etc.)
- US04/US05 labels + 600 DPI printed scans
- US02 printed scan
- Tracking + customer-facing tracking screenshot
- Three required PDFs (cover sheet, PIW, customer screenshots)
- **Comprehensive Rate Quote** — currently blocked by FedEx sandbox HTTP 403 (entitlement; contact FedEx support)

Use **Export diagnostic bundle** to review progress; **Export final FedEx package** unlocks only when preflight passes.

---

## Cleanup (after final ZIP)

Safe to remove after successful final export:

| Item | Reason |
|------|--------|
| `tmp_debug.php` (if present) | Session debug script |
| Old folders under `storage/app/fedex-validation/` | Keep latest final ZIP only |
| Duplicate FedEx carrier accounts on same integrator number | UX confusion |

**Keep:** baseline XLSX, Sweden `.env` keys, all validation service code.

---

## Next step — Package 4

Package 4 scope is not yet defined in project docs. Provide Package 4 guidance (same format as Packages 1–3) before implementation.

Suggested order until final submission:

1. Complete remaining validation workspace checks
2. Implement Package 4 (when guidance available)
3. Resolve Rate Quote entitlement with FedEx support
4. Build and submit final validation ZIP

---

## Related docs

- `docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md` — Model A architecture overview
- `docs/FEDEX_MODEL_A_INTEGRATOR_PROVIDER_ROADMAP.md` — Phase 6C-4/6C-5 roadmap
- Desktop `Guidance.txt` — Original Package 3 specification (Packages 1–2 guidance was used in prior sessions)
