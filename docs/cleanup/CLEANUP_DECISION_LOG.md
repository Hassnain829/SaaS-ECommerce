# Cleanup Decision Log

Record of hygiene decisions during CLEAN-1 and future cleanup phases.

## Format

| Date | Item | Decision | Reason |
|------|------|----------|--------|
| 2026-06-23 | FedEx validation labels/uploads | **Historical note** | At CLEAN-1 time these were preserved as evidence. After FedEx approval, certification/validation runtime trees and tables were intentionally retired; production business records remain protected. Do not restore validation workspace tooling. |
| 2026-06-23 | FedEx staging bundle directories | **Eligible for cleanup** | Temporary export staging only |
| 2026-06-23 | `carrier_api_events` database rows | **Preserve** | Canonical API evidence |
| 2026-06-23 | `.env` | **Never track/archive** | Secrets |
| 2026-06-23 | `vendor/`, `node_modules/` | **Never track/archive** | Reproducible dependencies |
| 2026-06-23 | Historical phase reports mentioning Model B as active | **Keep files; update canonical docs** | Audit history vs current strategy |
| 2026-06-23 | Admin panel | **No CLEAN-1 changes** | Explicitly out of scope |
| 2026-06-23 | Merchant/carrier business logic | **No CLEAN-1 changes** | Explicitly out of scope |
| 2026-06-23 | Broad `.env.* export-ignore` | **Replaced (CLEAN-1A)** | Incorrectly excluded `.env.example` from archives |
| 2026-06-23 | Laravel storage `.gitignore` placeholders | **Preserve in archives and cleanup** | Required for writable directories after deploy |
| 2026-06-23 | Git-tracked files | **Never cleanup targets (CLEAN-1A)** | `project:cleanup --force` uses `git ls-files` protection |
| 2026-06-23 | Non-Git ZIP archive fallback | **Removed (CLEAN-1A)** | Unreachable/dead; Git is required for safe archives |
| 2026-06-23 | Carrier code organization (CLEAN-2) | **Completed** | 59 git mv operations; no behavior change |
| 2026-06-23 | Runtime storage retention (CLEAN-3) | **Completed** | `project:retention`, protection markers, scheduler disabled by default |
| 2026-06-23 | Retention test isolation (CLEAN-3A) | **Completed** | Marked sandboxes; testing `--force` blocked against real worktree |
| 2026-06-24 | Controlled refactoring (CLEAN-4) | **Completed** | Four extractions with characterization tests; no behavior change |
| 2026-06-24 | Overlap pairs (test vs validation controllers) | **Keep both** | Reviewed in CLEAN-4; distinct responsibilities — see `docs/architecture/REFACTORING_BOUNDARIES.md` |
| 2026-08-12 | `.agents/` guidance files | **Deleted** | Obsolete duplicated guidance superseded by root docs + `.cursor/rules` + `docs/current/PROJECT_STATE.md` |
| 2026-08-12 | Generated QA bundles/command outputs | **Deleted** | Stale generated snapshots (`ENTERPRISE_QA_AUDIT_BUNDLE`, command outputs, QA docx) |
| 2026-08-12 | `tools/generate_qa_audit_bundle.py`, `tools/generate_qa_command_outputs.py` | **Deleted** | Obsolete generators; no CI/Composer/npm/Artisan dependency found |
| 2026-08-12 | Historical phase/audit/implementation/UX/FedEx reports | **Moved to `docs/archive/`** | Immutable historical evidence; not current instructions |
| 2026-08-12 | `docs/archive/**` | **Excluded from Cursor and source exports** | `.cursorignore` + `.gitattributes` `export-ignore` |
| 2026-08-12 | Root/current documentation | **Repaired** | Authority order + FedEx/USPS/DHL current state centralized in `docs/current/PROJECT_STATE.md` |
| 2026-08-12 | Application code / migrations / tests / carriers / merchant data | **Unchanged** | Documentation and AI-context cleanup only |

## Open items (future work)

- Later: split deferred oversized targets (`Store\OnboardingController`, `Store\DashboardController`, etc.) per `docs/architecture/REFACTORING_ROADMAP.md`
