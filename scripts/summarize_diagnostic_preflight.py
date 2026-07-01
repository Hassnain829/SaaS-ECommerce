import json
from collections import Counter
from pathlib import Path

root = Path("storage/app/temp-diagnostic-zip/FedEx_Integrator_Validation_BaasPlatformFedExSandbox")
preflight = json.loads((root / "preflight-report.json").read_text(encoding="utf-8"))
readme = (root / "README.md").read_text(encoding="utf-8")

print("READY:", preflight.get("ready"))
print("COMPLETED:", preflight.get("completed_count"), "/", preflight.get("total_count"))
print("BLOCKERS:", len(preflight.get("blockers", [])))
print()
print("STATUS COUNTS:")
for status, count in sorted(Counter(c.get("status") for c in preflight.get("checks", [])).items()):
    print(f"  {status}: {count}")
print()
print("BLOCKERS:")
for b in preflight.get("blockers", []):
    print(f"  - [{b.get('status')}] {b.get('label')}")
print()
print("PASSED:")
for c in preflight.get("checks", []):
    if c.get("status") == "passed":
        print(f"  + {c.get('key')}")
print()
print("NOT APPLICABLE:")
for c in preflight.get("checks", []):
    if c.get("status") == "not_applicable":
        print(f"  ~ {c.get('key')}")
print()
print("README:")
for line in readme.splitlines()[:6]:
    print(line)
