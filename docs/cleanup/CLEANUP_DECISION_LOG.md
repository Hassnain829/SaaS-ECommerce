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
| 2026-06-23 | FedEx Model A roadmap filename | **Renamed (CLEAN-1A)** | `docs/FEDEX_MODEL_A_INTEGRATOR_PROVIDER_ROADMAP.md` |

## Open items (future phases)

- CLEAN-2: carrier folder organization review
- CLEAN-3: scheduled pruning for logs and old staging exports
- CLEAN-4: split oversized controllers/services per `docs/REFACTORING_ROADMAP.md`
- Later: move validation/dev tools to admin side when admin migration is scheduled
