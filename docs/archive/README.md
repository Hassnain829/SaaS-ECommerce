# Archive Policy

docs/archive/** holds **immutable historical evidence**: phase completion reports, audits, implementation slices, UX delivery records, cleanup reports, and older FedEx implementation notes.

## Rules

* Archived reports may describe earlier implementation states, flags, blockers, or architecture.
* Their old status statements are **not** current instructions.
* They must never override current source code or active documentation.
* Cursor must ignore this archive (see .cursorignore).
* Source ZIP exports exclude this archive (see .gitattributes export-ignore).
* Files should not be moved back into active docs without explicit review.
* Old links inside archived snapshots may still reference their original pre-move paths.
* Git history preserves deletions and moves, including deleted .agents content.

## Authority

Prefer this order on conflict:

1. Current source code, migrations, routes, configuration, and tests
2. docs/current/PROJECT_STATE.md
3. Active handoffs, architecture, FedEx Model A docs, and root canonical documents
4. Active plans
5. This archive — historical only

## Subdirectories

| Folder | Purpose |
|--------|---------|
| phases/ | Phase completion reports |
| implementation/ | Slice/batch implementation reports |
|
eports/ | Standalone historical reports |
| ux/ | Historical UX delivery/acceptance records |
| udit/ | QA snapshots and gap registers |
| cleanup/ | CLEAN-1–4 historical reports and master plan |
|
edex/ | Historical FedEx implementation notes |
| handoffs/ | Obsolete handoffs |
| plans/ | Completed/superseded plans |
