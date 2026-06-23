# Cleanup Decision Log

Record of hygiene decisions during CLEAN-1 and future cleanup phases.

## Format

| Date | Item | Decision | Reason |
|------|------|----------|--------|
| 2026-06-23 | FedEx validation labels/uploads | **Preserve** | Canonical merchant/FedEx evidence |
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
| 2026-06-24 | Overlap pairs (test vs validation controllers) | **Keep both** | Reviewed in CLEAN-4; distinct responsibilities — see `REFACTORING_BOUNDARIES.md` |

## Open items (future work)

- Later: split deferred oversized targets (`OnboardingController`, `DashboardController`, etc.) per `docs/REFACTORING_ROADMAP.md`
- Later: move validation/dev tools to admin side when admin migration is scheduled
