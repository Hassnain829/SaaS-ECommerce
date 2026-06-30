# FedEx Integrator Validation — Packages 1–5 Session Report

**Date:** 30 June 2026 (updated)  
**Scope:** FedEx Model A integrator validation workspace — **Demo Digital** store (#2), sandbox account ending **7037** (`700257037`)  
**Status:** Packages 1–5 implemented and verified; latest diagnostic ZIP reviewed; final submission still blocked on remaining checklist items (ship labels, MFA, documents, etc.).

---

## Executive summary

This session completed guided implementation packages for FedEx integrator validation evidence:

| Package | Focus | Status |
|---------|--------|--------|
| **1** | Evidence sanitizer — preserve `shippingChargesPayment`, redact secrets | Done |
| **2** | Parent + Child OAuth authorization (one-click, fresh network calls) | Done |
| **3** | Sweden MFA Passthrough (validation-only, no account mutation) | Done |
| **4** | Official Hosted FedEx EULA (PDF.js viewer, scroll/accept, export folder) | Done |
| **5** | Comprehensive Rates & Transit Times (correct endpoint, UI match, screenshot evidence) | Done |
| **Hardening** | Secret scan, OAuth preflight strictness, export regression tests | Done |

Sweden MFA Passthrough was unblocked by correcting the workbook address format: street line must be `HAGAGATAN 1, VI` (comma included), not `VI` as a separate state field.

Package 5 was unblocked by switching certification evidence from the legacy standard Rate API (`POST /rate/v1/rates/quotes`) to the **Comprehensive Rates** endpoint required by the integrator child OAuth scope (`POST /rate/v1/comprehensiverates/quotes`). Live sandbox run returned HTTP **200**, **PRIORITY_OVERNIGHT**, **ACCOUNT** rate **USD 159.42**, with UI/response match and screenshot uploaded.

**Latest diagnostic ZIP reviewed:** `fedex-validation-diagnostic-2-20260630_000019.zip` — structure and Package 1–5 evidence are **correct for diagnostic export**. Final FedEx submission is **not yet ready** (`ready: false`, **17/40** checks, 43%).

---

## Diagnostic ZIP verification (30 Jun 2026 — latest)

**File:** `fedex-validation-diagnostic-2-20260630_000019.zip`  
**Store / account (from README):** Demo Digital (#2), carrier *****7037  
**Mode:** diagnostic — `INCOMPLETE — NOT READY FOR FEDEX SUBMISSION`

### Structure — passed

| Area | In ZIP | Verdict |
|------|--------|---------|
| Parent authorization | `01_registration_mfa/01_parent_authorization/` | OK — `grant_type: client_credentials`, secrets redacted |
| Child authorization | `01_registration_mfa/02_child_authorization/` | OK — `grant_type: csp_credentials`, child fields redacted |
| Registration MFA events | `03`–`11` folders | OK — SMS PIN, invoice, child credentials present |
| Sweden passthrough | `12_sweden_mfa_passthrough/` | OK — address `HAGAGATAN 1, VI`, `direct_child_authorization: passed` |
| **Hosted EULA (Package 4)** | `13_hosted_eula/` | OK — official PDF, acceptance record, UI screenshots |
| Address / service API | `02_`, `03_` | OK |
| **Comprehensive rates (Package 5)** | `04_comprehensive_rates/` | OK — see below |
| Ship scenarios | `05`–`07` | Placeholder `missing` — labels not run yet (expected) |
| Secret scan | All JSON | OK — Authorization headers redacted; no raw tokens or child secrets |
| Preflight report | `preflight-report.json` | OK — honest **17/40** complete |

### Package 5 folder — passed

```
04_comprehensive_rates/
├── request.json              (IntegratorUS02 fixture — Aurora OH → Collierville TN)
├── response.json             (HTTP 200, transactionId present, rateReplyDetails)
├── result_summary.json       (canonical parsed amount + UI match flags)
└── 01_customer_rate_result.png (169,296 bytes — merchant-uploaded screenshot)
```

**Canonical event:** `event_id: 42`, scenario `rate_comprehensive_quote`  
**Endpoint:** `POST /rate/v1/comprehensiverates/quotes`  
**HTTP status:** 200  
**Service:** PRIORITY_OVERNIGHT · **Rate type:** ACCOUNT · **Currency:** USD · **Amount:** **159.42**  
**UI match:** `ui_matches_response: true`, `displayed_amount` = `response_amount` = `159.42`  
**Submission ready:** `submission_ready: true`

**Request highlights (locked fixture):**

- `rateRequestControlParameters.returnTransitTimes: true`
- `rateRequestControlParameters.servicesNeededOnRateFailure: true`
- Shipper: Aurora OH 44202 → Recipient: Collierville TN 38017
- Service: PRIORITY_OVERNIGHT · Pickup: DROPOFF_AT_FEDEX_LOCATION · Packaging: YOUR_PACKAGING

**Merchant verification (30 Jun 2026):** Shipping automation comprehensive rate test **Passed**; validation workspace shows HTTP transaction **Passed**, UI/response match **Passed**, and comprehensive rate screenshot **uploaded** on Demo Digital.

### Preflight — Package 1–5 checks in this ZIP

| Check | Status |
|-------|--------|
| Parent / Child authorization | passed |
| Sweden passthrough address + child auth | passed |
| Hosted EULA (document + acceptance + UI + confirmation) | passed |
| **Comprehensive rate transaction** | **passed** (event 42) |
| **Comprehensive rate UI/response match** | **passed** (event 42) |
| **Comprehensive rate screenshot** | **passed** (artifact 5) |
| Sweden passthrough screenshots | **incomplete** (upload pending on workspace) |
| Registration address validation | **failed** (event 31 — re-run needed) |
| Ship labels / scans / tracking / 3 PDFs | **incomplete / not_tested** |

**Conclusion:** The ZIP is a **valid diagnostic bundle** for progress review including Package 5. It is **not** the final FedEx submission package until remaining blockers are cleared and **Export final FedEx package** passes preflight.

### Previous diagnostic ZIP (29 Jun 2026 — superseded for rates)

**File:** `fedex-validation-diagnostic-2-20260629_225513.zip` — contained legacy `04_rates/` with HTTP **403** on `POST /rate/v1/rates/quotes`. That failure was an **application endpoint mismatch**, not a FedEx entitlement block. Do **not** use legacy `rate_quote` events or `04_rates/` for certification evidence.

### Package 4 folder (included in latest ZIP)

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

## Package 5 — Comprehensive Rates & Transit Times

### Problem

Certification evidence was calling the **legacy standard Rate API**:

```text
POST /rate/v1/rates/quotes
```

Integrator child OAuth scope is **`comprehensive_rates`**, which requires:

```text
POST /rate/v1/comprehensiverates/quotes
```

The legacy endpoint returned HTTP **403** even though the sandbox account was entitled — the failure was an **endpoint and payload mismatch**, not a FedEx support entitlement issue. Legacy `rate_quote` scenario events must **never** qualify as Package 5 evidence.

### Critical architectural decision

Do **not** replace every rate caller globally. Merchant developer test tools may keep the existing `FedExRateQuoteService`. Certification uses a **dedicated comprehensive rate service** only.

### Implementation

| Component | Purpose |
|-----------|---------|
| `FedExComprehensiveRateQuoteService.php` | HTTP call to comprehensive endpoint |
| `FedExComprehensiveRatePayloadFactory.php` | Locked payload from IntegratorUS02 baseline |
| `FedExComprehensiveRateResponseParser.php` | Deterministic service/rate/amount parsing |
| `FedExComprehensiveRateAccessClassifier.php` | Classifies 403 (wrong endpoint vs real entitlement) |
| `FedExComprehensiveRateResult.php` | Typed result DTO |
| `FedExComprehensiveRateEvidenceService.php` | Workspace status, UI match, export assembly |
| Scenario key | `rate_comprehensive_quote` |
| Export folder | `04_comprehensive_rates/` (replaces legacy `04_rates/`) |
| Workspace UI | **Run Comprehensive Rate Quote** + screenshot upload |
| Routes | `POST .../validation/run/comprehensive-rate`, `.../comprehensive-rate-screenshot` |

### Locked fixture (IntegratorUS02 baseline)

| Field | Value |
|-------|--------|
| Origin | Aurora OH 44202 (US) |
| Destination | Collierville TN 38017 (US) |
| Service | PRIORITY_OVERNIGHT |
| Pickup type | DROPOFF_AT_FEDEX_LOCATION |
| Packaging | YOUR_PACKAGING |
| Control params | `returnTransitTimes: true`, `servicesNeededOnRateFailure: true` |

### Preflight checks (3 required)

- `comprehensive_rate_transaction` — successful HTTP call on required endpoint
- `comprehensive_rate_ui_match` — customer-facing panel amount matches stored canonical response
- `comprehensive_rate_screenshot` — merchant-uploaded screenshot linked to canonical event

### Verified working state (30 Jun 2026)

| Property | Value |
|----------|--------|
| HTTP status | 200 |
| Endpoint | `/rate/v1/comprehensiverates/quotes` |
| Service | PRIORITY_OVERNIGHT |
| Rate type | ACCOUNT |
| Currency | USD |
| Amount | **159.42** |
| UI match | Passed |
| Screenshot | Uploaded (`01_customer_rate_result.png` in export) |
| Canonical event | 42 |

Legacy `runRateQuote()` on the validation controller **delegates** to comprehensive rate for certification. Integrator rate test in `FedExCarrierTestController` also uses the comprehensive service.

### `.env` key

```env
FEDEX_COMPREHENSIVE_RATE_PATH=/rate/v1/comprehensiverates/quotes
```

(Config default in `config/carriers.php` — `comprehensive_rate_quote_path`.)

### Tests

- `tests/Feature/Phase6FedExComprehensiveRateValidationTest.php` (8 tests)
- Updated: `Phase6FedExValidationCorrectionTest`, `Phase6FedExValidationExportTest`, `Phase6FedExShipValidationTest` for `04_comprehensive_rates/`

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

**~185 tests** at last full run (includes Packages 1–5 + hardening). One unrelated sandbox residential payload ordering test may fail intermittently.

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
| `app/Services/Carriers/FedEx/Operations/FedExComprehensiveRateQuoteService.php` | Package 5 comprehensive rate HTTP |
| `app/Services/Carriers/FedEx/Operations/FedExComprehensiveRatePayloadFactory.php` | Package 5 locked payload |
| `app/Services/Carriers/FedEx/Validation/FedExComprehensiveRateEvidenceService.php` | Package 5 preflight + export |
| `app/Services/Carriers/FedEx/Validation/FedExValidationPreflightService.php` | Readiness checks before final export |
| `app/Services/Carriers/FedEx/Validation/FedExValidationEvidenceExporter.php` | Diagnostic + final ZIP |
| `resources/views/user_view/fedex_validation/workspace.blade.php` | Validation workspace UI |
| `docs/fedex/FedEx_Integrator_Test_Case_Baseline.xlsx` | FedEx baseline workbook |

---

## Workspace validation progress (merchant checklist)

Packages 1–5 code is complete on **Demo Digital / carrier account #2**. **Final FedEx submission** still requires (~23 remaining checks from 40 total), including:

- Sweden passthrough **screenshots** (API evidence done; uploads pending)
- Email + phone-call PIN MFA steps
- Re-run or fix **registration address validation** (latest event failed in preflight)
- US02/US04/US05 **locked ship labels** + 600 DPI printed scans
- Tracking + customer-facing tracking screenshot
- Three required PDFs (cover sheet, PIW, customer screenshots)

**Package 5 (Comprehensive Rates) is complete** — HTTP 200, USD 159.42, UI match passed, screenshot uploaded.

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

1. **Package 6** — next guided implementation package (ship label flows US02/US04/US05 are the primary remaining API evidence blockers)
2. Upload Sweden passthrough screenshots on Demo Digital workspace
3. Complete email/call PIN MFA + fix registration address validation
4. Run locked ship label flows (US02/US04/US05) + printed scans
5. Tracking + screenshot + 3 required PDF documents
6. When preflight shows **ready** → **Export final FedEx package** from Demo Digital account #2

---

## Related docs

- `docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md` — Model A architecture overview
- `docs/FEDEX_MODEL_A_INTEGRATOR_PROVIDER_ROADMAP.md` — Phase 6C-4/6C-5 roadmap
- Desktop `Guidance.txt` — Package 4 EULA and Package 5 Comprehensive Rates specifications
