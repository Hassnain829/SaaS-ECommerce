# FedEx Integrator Validation — Packages 1–4 Session Report

**Date:** 30 June 2026 (updated)  
**Scope:** FedEx Model A integrator validation workspace — **Demo Digital** store (#2), sandbox account ending **7037** (`700257037`)  
**Status:** Packages 1–4 implemented and verified; diagnostic ZIP reviewed; final submission still blocked on remaining checklist items.

---

## Executive summary

This session completed guided implementation packages for FedEx integrator validation evidence:

| Package | Focus | Status |
|---------|--------|--------|
| **1** | Evidence sanitizer — preserve `shippingChargesPayment`, redact secrets | Done |
| **2** | Parent + Child OAuth authorization (one-click, fresh network calls) | Done |
| **3** | Sweden MFA Passthrough (validation-only, no account mutation) | Done |
| **4** | Official Hosted FedEx EULA (PDF.js viewer, scroll/accept, export folder) | Done |
| **Hardening** | Secret scan, OAuth preflight strictness, export regression tests | Done |

Sweden MFA Passthrough was unblocked by correcting the workbook address format: street line must be `HAGAGATAN 1, VI` (comma included), not `VI` as a separate state field.

**Latest diagnostic ZIP reviewed:** `fedex-validation-diagnostic-2-20260629_225513.zip` — structure and Package 1–4 evidence are **correct for diagnostic export**. Final FedEx submission is **not yet ready** (`ready: false`, 14/38 checks).

---

## Diagnostic ZIP verification (29 Jun 2026)

**File:** `fedex-validation-diagnostic-2-20260629_225513.zip`  
**Store / account (from README):** Demo Digital (#2), carrier *****7037  
**Mode:** diagnostic — `INCOMPLETE — NOT READY FOR FEDEX SUBMISSION`

### Structure — passed

| Area | In ZIP | Verdict |
|------|--------|---------|
| Parent authorization | `01_registration_mfa/01_parent_authorization/` | OK — `grant_type: client_credentials`, secrets redacted |
| Child authorization | `01_registration_mfa/02_child_authorization/` | OK — `grant_type: csp_credentials`, child fields redacted |
| Registration MFA events | `03`–`11` folders | OK — SMS PIN, invoice, child credentials present |
| Sweden passthrough | `12_sweden_mfa_passthrough/` | OK — address `HAGAGATAN 1, VI`, `direct_child_authorization: passed` |
| **Hosted EULA (Package 4)** | `13_hosted_eula/` | OK — see below |
| Address / service API | `02_`, `03_` | OK |
| Rate quote | `04_rates/` | OK — HTTP 403 entitlement documented (expected blocker) |
| Ship scenarios | `05`–`07` | Placeholder `missing` — labels not run yet (expected) |
| Secret scan | All JSON | OK — no raw tokens, account numbers, or child secrets found |
| Preflight report | `preflight-report.json` | OK — honest 14/38 complete |

### Package 4 folder — passed

```
01_registration_mfa/13_hosted_eula/
├── official_eula.pdf              (229,715 bytes)
├── eula_document_metadata.json    (SHA256 matches official PDF)
├── eula_acceptance_record.json    (accepted, scroll + read ack, button "I accept")
└── screenshots/
    ├── 01_full_hosted_eula_ui.pdf (1,201,053 bytes)
    └── 02_acceptance_confirmation.png (523,835 bytes)
```

**Official PDF hash:** `3eea76a66fbae1d798c2069934ec9c2750c75e8f47879697cec16c84c47e8ab8`  
**Form:** FedEx Form No. 2002382 v 4 June 2024 Rev, 11 pages  
**Acceptance timestamp:** 2026-06-29T22:52:30+00:00

### Preflight — Package 1–4 checks in this ZIP

| Check | Status |
|-------|--------|
| Parent / Child authorization | passed |
| Sweden passthrough address + child auth | passed |
| Hosted EULA document | passed |
| Hosted EULA acceptance | passed |
| Hosted EULA full UI evidence | passed |
| Hosted EULA acceptance confirmation | passed |
| Sweden passthrough screenshots | **incomplete** (not in ZIP — upload pending on workspace) |
| Rate quote | **blocked** (HTTP 403 — FedEx entitlement) |
| Ship labels / scans / tracking / 3 PDFs | **incomplete / not_tested** |

**Conclusion:** The ZIP is a **valid diagnostic bundle** for progress review. It is **not** the final FedEx submission package until remaining blockers are cleared and **Export final FedEx package** passes preflight.

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

Correct `.env` fallback:

```env
FEDEX_VALIDATION_SWEDEN_ACCOUNT_NUMBER=604849268
FEDEX_VALIDATION_SWEDEN_CUSTOMER_NAME="Unique Customer Name"
FEDEX_VALIDATION_SWEDEN_ADDRESS_LINE1="HAGAGATAN 1, VI"
FEDEX_VALIDATION_SWEDEN_CITY=STOCKHOLM
FEDEX_VALIDATION_SWEDEN_POSTAL_CODE=11349
FEDEX_VALIDATION_SWEDEN_COUNTRY_CODE=SE
```

Parser fix: `FedExTestCaseFixtureService::parseSwedenParentheticalAddress()` merges the second token into street line 1.

### Screenshots required (after successful run)

1. **Address/passthrough result** — Sweden card showing Passed / child credentials / MFA bypassed
2. **Direct child authorization** — same card with Direct child authorization: Passed

### Tests

- `tests/Feature/Phase6FedExSwedenPassthroughTest.php` (11 tests)

---

## Package 4 — Official Hosted FedEx EULA

### Goal

FedEx certification requires the **exact official hosted third-party EULA PDF** rendered inside the application, with merchant scroll-through, read acknowledgement, exact **"I accept"** button, and exportable evidence — not HTML placeholder or auto-accept.

### Official document

| Property | Value |
|----------|--------|
| File | `resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf` |
| Form | 2002382 — FedEx Form No. 2002382 v 4 June 2024 Rev |
| Pages | 11 |
| SHA256 | `3eea76a66fbae1d798c2069934ec9c2750c75e8f47879697cec16c84c47e8ab8` |

### Implementation

| Component | Purpose |
|-----------|---------|
| `FedExEulaService.php` | PDF validation, hash, metadata (`isValid()`, no HTML placeholder) |
| `FedExHostedEulaEvidenceService.php` | Preflight + workspace status for EULA |
| `fedex-eula-viewer.js` | PDF.js — render all 11 pages, scroll completion, print evidence |
| `eula.blade.php` | PDF viewer UI, checkbox, **I accept**, Print / Save EULA evidence |
| Migration | `purpose`, `eula_document_hash`, scroll/read ack fields on sessions; hash on accounts |
| Validation-only path | **Review and accept Hosted EULA** — no reconnect, no credential mutation |
| Export folder | `01_registration_mfa/13_hosted_eula/` |
| Routes | `eula/document`, `eula/scroll-complete`, `validation/run/eula-review`, `validation/eula-evidence` |

### Preflight checks (4 required)

- `hosted_eula_document`
- `hosted_eula_acceptance`
- `hosted_eula_full_ui_evidence`
- `hosted_eula_acceptance_confirmation`

Legacy acceptance (`eula_version=1.0`, null hash) is treated as **outdated** — must re-accept official PDF.

### Merchant workflow (Demo Digital — same carrier account as other tests)

1. Validation workspace → **Review and accept Hosted EULA** (do **not** reconnect FedEx)
2. Scroll all 11 pages → checkbox → **I accept**
3. **Print / Save EULA evidence** → multi-page PDF (File 1)
4. Screenshot after acceptance / Passed status (File 2)
5. Upload both on workspace **Upload EULA Evidence**

### UI fixes applied during Package 4

- PDF.js auto-init (module load order vs Alpine `x-init`)
- Print CSS — scroll container expands so all 11 pages export to PDF (not 1 clipped page)
- `beforeprint` handler removes `max-height` on scroll viewport

### Tests

- `tests/Feature/Phase6FedExHostedEulaComplianceTest.php` (8 tests)
- Updated `Phase6FedExModelAIntegratorProviderTest` EULA acceptance tests

### `.env` keys

```env
FEDEX_INTEGRATOR_EULA_VERSION="FedEx Form No. 2002382 v 4 June 2024 Rev"
FEDEX_INTEGRATOR_EULA_PATH=resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf
FEDEX_INTEGRATOR_EULA_FORM_NUMBER=2002382
FEDEX_INTEGRATOR_EULA_EXPECTED_PAGES=11
FEDEX_INTEGRATOR_EULA_SHA256=3eea76a66fbae1d798c2069934ec9c2750c75e8f47879697cec16c84c47e8ab8
```

---

## Post-Package-2 hardening (fixes.txt)

### Fix 1 — Secret scanner bypass (critical)

Removed file-level `[REDACTED]` exemption in `FedExValidationEvidenceSanitizer::scanStagingDirectory()`.

### Fix 3 & 4 — OAuth preflight strictness

**New class:** `FedExValidationAuthorizationEvidenceRules.php` — canonical OAuth selection uses strict grant-type and redaction rules.

### Package 3 hardening (with Package 4)

- Sweden child OAuth uses `FedExValidationAuthorizationEvidenceRules`
- `result_summary.json` derived from event data (not hardcoded)

---

## Test summary

```bash
php artisan test --filter="Phase6FedEx"
```

**177 tests** at last full run (includes Packages 1–4 + hardening). One unrelated sandbox residential payload ordering test may fail intermittently.

---

## Key file index

| Path | Role |
|------|------|
| `app/Services/Carriers/FedEx/Connection/FedExEulaService.php` | Official PDF validation |
| `app/Services/Carriers/FedEx/Validation/FedExHostedEulaEvidenceService.php` | EULA preflight + workspace status |
| `resources/js/fedex-eula-viewer.js` | PDF.js viewer |
| `resources/views/user_view/fedex_integrator/eula.blade.php` | EULA acceptance page |
| `resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf` | Official EULA |
| `app/Services/Carriers/FedEx/Validation/FedExSensitiveFieldClassifier.php` | Sensitive field detection |
| `app/Services/Carriers/FedEx/Validation/FedExValidationEvidenceSanitizer.php` | Redaction + export secret scan |
| `app/Services/Carriers/FedEx/Validation/FedExValidationAuthorizationEvidenceService.php` | Package 2 OAuth runner |
| `app/Services/Carriers/FedEx/Validation/FedExValidationAuthorizationEvidenceRules.php` | OAuth evidence validation rules |
| `app/Services/Carriers/FedEx/Validation/FedExValidationSwedenPassthroughService.php` | Package 3 orchestrator |
| `app/Services/Carriers/FedEx/Validation/FedExValidationPreflightService.php` | Readiness checks before final export |
| `app/Services/Carriers/FedEx/Validation/FedExValidationEvidenceExporter.php` | Diagnostic + final ZIP |
| `resources/views/user_view/fedex_validation/workspace.blade.php` | Validation workspace UI |
| `docs/fedex/FedEx_Integrator_Test_Case_Baseline.xlsx` | FedEx baseline workbook |

---

## Workspace validation progress (merchant checklist)

Packages 1–4 code is complete on **Demo Digital / carrier account #2**. **Final FedEx submission** still requires (~24 remaining checks from 38 total), including:

- Sweden passthrough **screenshots** (API evidence done; uploads pending in latest ZIP)
- Email + phone-call PIN MFA steps
- Re-run or fix **registration address validation** (latest event failed in preflight)
- US02/US04/US05 **locked ship labels** + 600 DPI printed scans
- Tracking + customer-facing tracking screenshot
- Three required PDFs (cover sheet, PIW, customer screenshots)
- **Comprehensive Rate Quote** — HTTP 403 FedEx entitlement (contact FedEx support)

Use **Export diagnostic bundle** to review progress; **Export final FedEx package** unlocks only when preflight `ready: true`.

### Store scoping reminder

All evidence for final submission must live on **one store + one carrier account** (Demo Digital, account ending 7037). EULA completed on Demo Fashion does **not** count toward Demo Digital's bundle. Use **Review and accept Hosted EULA** on the existing account — **do not reconnect** a second FedEx connection on the same store.

---

## Cleanup (after final ZIP)

| Item | Reason |
|------|--------|
| `tmp_debug.php` (if present) | Session debug script |
| Old folders under `storage/app/fedex-validation/` | Keep latest final ZIP only |
| Duplicate FedEx carrier accounts on same store | UX confusion — avoid second connection |

**Keep:** baseline XLSX, Sweden `.env` keys, official EULA PDF, all validation service code.

---

## Next steps toward final submission

1. Upload Sweden passthrough screenshots on Demo Digital workspace
2. Complete email/call PIN MFA + fix registration address validation
3. Run locked ship label flows (US02/US04/US05) + printed scans
4. Tracking + screenshot + 3 required PDF documents
5. Resolve Rate Quote entitlement with FedEx (or document blocked status per FedEx guidance)
6. When preflight shows **ready** → **Export final FedEx package** from Demo Digital account #2

---

## Related docs

- `docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md` — Model A architecture overview
- `docs/FEDEX_MODEL_A_INTEGRATOR_PROVIDER_ROADMAP.md` — Phase 6C-4/6C-5 roadmap
- Desktop `Guidance.txt` — Package 4 EULA specification
