#!/usr/bin/env python3
"""Read-only QA command output collector."""
from __future__ import annotations

import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "docs" / "audit" / "ENTERPRISE_QA_COMMAND_OUTPUTS.md"


def run(cmd: list[str], cwd: Path | None = None, timeout: int = 600) -> str:
    result = subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        cwd=cwd or ROOT,
        timeout=timeout,
        shell=True,
    )
    return ((result.stdout or "") + (result.stderr or "")).rstrip()


def section(title: str, cmd_display: str, output: str) -> list[str]:
    return [
        f"## {title}",
        "",
        "```powershell",
        cmd_display,
        "```",
        "",
        "```text",
        output,
        "```",
        "",
    ]


def main() -> None:
    lines = [
        "# Enterprise QA Command Outputs",
        "",
        f"Generated: {datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S UTC')}",
        "",
        "Safe read-only commands only. No `migrate:fresh`. Secrets excluded.",
        "",
    ]

    base_cmds = [
        ("composer validate", ["composer", "validate"], "composer validate"),
        ("composer dump-autoload", ["composer", "dump-autoload"], "composer dump-autoload"),
        ("php artisan optimize:clear", ["php", "artisan", "optimize:clear"], "php artisan optimize:clear"),
        ("php artisan about", ["php", "artisan", "about"], "php artisan about"),
        ("php artisan migrate:status", ["php", "artisan", "migrate:status"], "php artisan migrate:status"),
        ("php artisan route:list", ["php", "artisan", "route:list"], "php artisan route:list"),
        (
            "php artisan test --list-tests (last 40 lines)",
            ["php", "artisan", "test", "--list-tests"],
            "php artisan test --list-tests",
        ),
    ]

    for title, cmd, display in base_cmds:
        output = run(cmd)
        if title.startswith("php artisan test --list"):
            output = "\n".join(output.splitlines()[-40:])
        lines.extend(section(title, display, output))

    filters = [
        "Phase1",
        "Phase2",
        "Phase3",
        "Phase4",
        "Phase5",
        "Phase6",
        "Inventory",
        "Checkout",
        "Payment",
        "External",
        "Fulfillment",
        "Shipment",
        "Routing",
    ]
    for filt in filters:
        title = f"php artisan test --filter={filt}"
        output = run(["php", "artisan", "test", f"--filter={filt}"])
        lines.extend(section(title, title, output))

    lines.extend(
        section("php artisan test (full suite)", "php artisan test", run(["php", "artisan", "test"]))
    )

    npm = "npm.cmd" if Path("C:/Windows/System32/npm.cmd").exists() else "npm"
    lines.extend(
        section("npm run build", "npm run build", run([npm, "run", "build"]))
    )
    lines.extend(
        section(
            "dev-test-storefront npm run build",
            "cd dev-test-storefront && npm run build",
            run([npm, "run", "build"], cwd=ROOT / "dev-test-storefront"),
        )
    )

    OUT.write_text("\n".join(lines), encoding="utf-8")
    print(f"Wrote {OUT} ({OUT.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
