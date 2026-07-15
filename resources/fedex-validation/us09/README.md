# FedEx IntegratorUS09 validation assets

Before the final evidence run, place real workbook-quality files in this directory:

| Filename | Purpose | Constraints |
|----------|---------|-------------|
| `signature3.png` | Company letterhead / logo image | PNG or GIF, max **700×50** pixels |
| `signature2.png` | Authorized signature image | PNG or GIF, max **240×25** pixels |
| `commercial_invoice.pdf` | Visually clear commercial invoice | Valid PDF (`%PDF-` … `%%EOF`), larger than 1 KB, max 5 MB |

Do **not** use 1×1 placeholders or malformed tiny PDFs. The upload readiness checks reject them.

Fixtures continue to reference these exact filenames so the final run stays blocked until proper assets are supplied.

Do not generate a fake authorized signature for submission.
