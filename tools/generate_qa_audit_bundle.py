#!/usr/bin/env python3
"""Read-only QA audit bundle generator. Does not modify source code."""
from __future__ import annotations

import os
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "ENTERPRISE_QA_AUDIT_BUNDLE.md"

SECRET_PATTERNS = [
    re.compile(r"sk_(live|test)_[A-Za-z0-9]+"),
    re.compile(r"pk_(live|test)_[A-Za-z0-9]+"),
    re.compile(r"whsec_[A-Za-z0-9]+"),
    re.compile(r"base64:[A-Za-z0-9+/=]+"),
    re.compile(r"Bearer\s+[A-Za-z0-9._-]+"),
]

DOCS = [
    "AGENTS.md",
    "ENTERPRISE_PROJECT_CONTEXT.md",
    "ENTERPRISE_ROADMAP_2026.md",
    "PROJECT_BRAIN.md",
    ".agents/rules/PROJECT-CONTEXT.txt",
    ".agents/rules/ROADMAP.txt",
    ".agents/rules/OVERVIEW FOR SAAS.txt",
    "docs/LOCAL_SETUP.md",
    "docs/RELEASE_CHECKLIST.md",
    "docs/SECURITY_HARDENING.md",
    "docs/REFACTORING_ROADMAP.md",
    "docs/ORDER_LIFECYCLE_HARDENING_REPORT.md",
    "docs/PHASE_1_SAAS_FOUNDATION_HARDENING_REPORT.md",
    "docs/PHASE_2_CATALOG_COMPLETION_REPORT.md",
    "docs/PHASE_3_ENTERPRISE_INVENTORY_REPORT.md",
    "docs/PHASE_4_COMMERCE_CORE_COMPLETION_REPORT.md",
    "docs/PHASE_5_EXTERNAL_CHECKOUT_SYNC_REPORT.md",
    "docs/PHASE_5_PLATFORM_CHECKOUT_STRIPE_SANDBOX_REPORT.md",
    "docs/PHASE_5_STRIPE_CONNECT_FOUNDATION_REPORT.md",
    "docs/STRIPE_SANDBOX_CONNECT_SUPPORT_REPORT.md",
    "docs/EXTERNAL_MANAGED_CHANNEL_MODE_REPORT.md",
    "docs/PHASE_6A_MANUAL_FULFILLMENT_REPORT.md",
    "docs/PHASE_6B_CHECKOUT_DELIVERY_METHODS_REPORT.md",
    "docs/PHASE_6C_0A_NEAREST_ELIGIBLE_ORIGIN_ROUTING_REPORT.md",
]

ROUTES = [
    "routes/web.php",
    "routes/api.php",
    "routes/channels.php",
    "routes/console.php",
]

CONFIG = [
    "composer.json",
    "package.json",
    "vite.config.js",
    "phpunit.xml",
    ".env.example",
    "config/app.php",
    "config/auth.php",
    "config/cache.php",
    "config/database.php",
    "config/filesystems.php",
    "config/mail.php",
    "config/payments.php",
    "config/services.php",
    "config/session.php",
    "config/queue.php",
]

MODELS = [
    "app/Models/User.php",
    "app/Models/Store.php",
    "app/Models/Location.php",
    "app/Models/Product.php",
    "app/Models/ProductVariant.php",
    "app/Models/ProductAttribute.php",
    "app/Models/InventoryItem.php",
    "app/Models/InventoryLevel.php",
    "app/Models/InventoryReservation.php",
    "app/Models/StockMovement.php",
    "app/Models/Customer.php",
    "app/Models/CustomerNote.php",
    "app/Models/CustomerTag.php",
    "app/Models/Checkout.php",
    "app/Models/CheckoutItem.php",
    "app/Models/CheckoutAddress.php",
    "app/Models/CheckoutEvent.php",
    "app/Models/Order.php",
    "app/Models/OrderItem.php",
    "app/Models/OrderAddress.php",
    "app/Models/OrderEvent.php",
    "app/Models/DraftOrder.php",
    "app/Models/DraftOrderItem.php",
    "app/Models/Shipment.php",
    "app/Models/ShipmentItem.php",
    "app/Models/Carrier.php",
    "app/Models/CarrierAccount.php",
    "app/Models/ShippingZone.php",
    "app/Models/ShippingMethod.php",
    "app/Models/PaymentIntent.php",
    "app/Models/PaymentProviderAccount.php",
    "app/Models/StoreShipmentSequence.php",
]

CONTROLLERS = [
    "app/Http/Controllers/DashboardController.php",
    "app/Http/Controllers/OnboardingController.php",
    "app/Http/Controllers/GeneralSettingsController.php",
    "app/Http/Controllers/LocationController.php",
    "app/Http/Controllers/ProductController.php",
    "app/Http/Controllers/InventoryController.php",
    "app/Http/Controllers/OrderController.php",
    "app/Http/Controllers/DraftOrderController.php",
    "app/Http/Controllers/CustomerController.php",
    "app/Http/Controllers/ShipmentController.php",
    "app/Http/Controllers/ShippingSettingsController.php",
    "app/Http/Controllers/PaymentSettingsController.php",
    "app/Http/Controllers/Api/PlatformCheckoutController.php",
    "app/Http/Controllers/Api/ExternalOrderSyncController.php",
]

FRONTEND = [
    "dev-test-storefront/package.json",
    "dev-test-storefront/.env.example",
    "dev-test-storefront/src/App.jsx",
]

MIGRATION_KEYWORDS = [
    "users", "stores", "roles", "permissions", "security", "sessions",
    "products", "variants", "attributes", "inventory", "locations",
    "reservations", "stock_movements", "customers", "checkouts",
    "checkout_events", "payment_intents", "payment_provider_accounts",
    "orders", "order_items", "order_addresses", "order_events",
    "draft_orders", "shipments", "shipment_items", "shipping_zones",
    "shipping_methods", "carrier", "external", "idempotency", "fulfillment",
]

TEST_KEYWORDS = [
    "Phase", "Saas", "Store", "Permission", "Security", "Catalog",
    "Product", "Variant", "Inventory", "Location", "Checkout", "Payment",
    "Stripe", "Connect", "External", "Order", "Draft", "Customer",
    "Shipping", "Shipment", "Fulfillment", "Delivery", "Routing",
]

SEARCH_TERMS = [
    "store_id", "currentStore", "authorize", "can:", "Gate::", "Policy",
    "permission", "role", "owner", "staff", "admin", "tenant", "cross-store",
    "transaction", "DB::transaction", "lockForUpdate", "reservation",
    "release", "deduct", "restore", "stock", "available", "payment_intent",
    "stripe", "connect", "webhook", "idempotency", "external_order",
    "external_checkout", "inventory_owner", "platform", "external",
    "fulfillment_status", "payment_status", "order_status", "shipment",
    "tracking", "origin_location_id", "fulfillment_origin_location_id",
    "pickup_location_id", "shipping_method_id", "shipping_snapshot",
    "shipping_zone", "postal", "postcode", "zip", "routing",
    "nearest_eligible_0a", "service_area_stock_priority", "failed", "exception",
    "throw", "abort(403", "abort(404",
]

SEARCH_DIRS = [
    "app", "routes", "resources/views", "database/migrations",
    "tests", "config", "dev-test-storefront/src",
]


def mask_secrets(text: str) -> str:
    for pattern in SECRET_PATTERNS:
        text = pattern.sub(lambda m: m.group(0)[:8] + "…[REDACTED]", text)
    return text


def fence_lang(path: Path) -> str:
    ext = path.suffix.lower()
    if ext == ".php":
        return "php"
    if ext in {".blade.php"} or path.name.endswith(".blade.php"):
        return "blade"
    if ext in {".jsx", ".js"}:
        return "jsx" if ext == ".jsx" else "javascript"
    if ext == ".json":
        return "json"
    if ext == ".xml":
        return "xml"
    if ext == ".md":
        return "markdown"
    return "txt"


def append_file(buf: list[str], rel: str) -> None:
    path = ROOT / rel.replace("/", os.sep)
    buf.append(f"\n---\n\n## FILE: {rel.replace(os.sep, '/')}\n\n")
    if not path.exists():
        buf.append("MISSING FILE\n")
        return
    try:
        content = path.read_text(encoding="utf-8", errors="replace")
    except OSError as exc:
        buf.append(f"UNREADABLE FILE: {exc}\n")
        return
    content = mask_secrets(content)
    lang = fence_lang(path)
    buf.append(f"```{lang}\n{content}\n```\n")


def collect_glob(pattern: str) -> list[str]:
    return sorted(str(p.relative_to(ROOT)).replace("\\", "/") for p in ROOT.glob(pattern))


def migration_files() -> list[str]:
    out = []
    for p in sorted((ROOT / "database/migrations").glob("*.php")):
        name = p.name.lower()
        text = p.read_text(encoding="utf-8", errors="replace").lower()
        if any(k in name or k in text for k in MIGRATION_KEYWORDS):
            out.append(str(p.relative_to(ROOT)).replace("\\", "/"))
    return out


def test_files() -> list[str]:
    files = []
    for base in ["tests/Feature", "tests/Unit"]:
        d = ROOT / base
        if not d.exists():
            continue
        for p in sorted(d.glob("*Test.php")):
            if any(k.lower() in p.name.lower() for k in TEST_KEYWORDS):
                files.append(str(p.relative_to(ROOT)).replace("\\", "/"))
    return files


def view_files() -> list[str]:
    keywords = [
        "layout", "dashboard", "onboarding", "settings", "location",
        "product", "inventory", "order", "draft", "customer", "shipping",
        "shipment", "payment", "checkout",
    ]
    out = []
    views = ROOT / "resources/views"
    for p in sorted(views.rglob("*.blade.php")):
        rel = str(p.relative_to(ROOT)).replace("\\", "/").lower()
        if any(k in rel for k in keywords):
            out.append(str(p.relative_to(ROOT)).replace("\\", "/"))
    return out


def schema_summary() -> str:
    tables = {
        "users": [], "stores": [], "locations": [], "products": [],
        "product_variants": [], "inventory_items": [], "inventory_levels": [],
        "inventory_reservations": [], "stock_movements": [], "customers": [],
        "checkouts": [], "checkout_items": [], "checkout_addresses": [],
        "checkout_events": [], "payment_intents": [], "payment_provider_accounts": [],
        "orders": [], "order_items": [], "order_addresses": [], "order_events": [],
        "draft_orders": [], "draft_order_items": [], "shipments": [],
        "shipment_items": [], "shipping_zones": [], "shipping_methods": [],
        "carrier_accounts": [],
    }
    lines = ["# Schema Summary\n"]
    for mig in migration_files():
        text = (ROOT / mig).read_text(encoding="utf-8", errors="replace")
        for table in tables:
            if f"Schema::create('{table}'" in text or f'Schema::create("{table}"' in text:
                tables[table].append(mig)
    for table, migs in tables.items():
        lines.append(f"\n## {table}\n")
        if not migs:
            lines.append("- **Status:** no dedicated create migration found in keyword scan\n")
            continue
        lines.append(f"- **Migrations:** {', '.join(migs)}\n")
        text = (ROOT / migs[0]).read_text(encoding="utf-8", errors="replace")
        has_store = "store_id" in text
        has_soft = "softDeletes" in text or "deleted_at" in text
        json_cols = re.findall(r"\$table->json\('([^']+)'\)", text)
        status_cols = [c for c in re.findall(r"\$table->\w+\('([^']*status[^']*)'", text, re.I)]
        money_cols = [c for c in re.findall(r"\$table->\w+\('([^']*(?:total|amount|price|subtotal|shipping|discount)[^']*)'", text, re.I)]
        lines.append(f"- **store_id present:** {'yes' if has_store else 'no'}\n")
        lines.append(f"- **soft deletes:** {'yes' if has_soft else 'no'}\n")
        if json_cols:
            lines.append(f"- **json columns:** {', '.join(json_cols)}\n")
        if status_cols:
            lines.append(f"- **status columns:** {', '.join(status_cols)}\n")
        if money_cols:
            lines.append(f"- **money-like columns:** {', '.join(sorted(set(money_cols)))}\n")
    return "".join(lines)


def search_evidence() -> str:
    lines = ["# Search Evidence\n", "\nGenerated via ripgrep-style scan. Secrets masked.\n\n"]
    for term in SEARCH_TERMS:
        lines.append(f"\n## Search: `{term}`\n\n")
        count = 0
        for d in SEARCH_DIRS:
            base = ROOT / d
            if not base.exists():
                continue
            for path in base.rglob("*"):
                if not path.is_file() or path.suffix not in {".php", ".blade.php", ".jsx", ".js", ".md"}:
                    continue
                try:
                    for i, line in enumerate(path.read_text(encoding="utf-8", errors="replace").splitlines(), 1):
                        if term.lower() in line.lower():
                            rel = str(path.relative_to(ROOT)).replace("\\", "/")
                            lines.append(f"{rel}:{i}: {mask_secrets(line.strip())[:200]}\n")
                            count += 1
                            if count >= 25:
                                break
                except OSError:
                    pass
                if count >= 25:
                    break
            if count >= 25:
                break
        if count == 0:
            lines.append("(no matches in scanned dirs)\n")
        elif count >= 25:
            lines.append(f"(truncated at 25 matches for `{term}`)\n")
    return "".join(lines)


def main() -> None:
    buf: list[str] = [
        "# ENTERPRISE QA AUDIT BUNDLE\n",
        "\nRead-only export generated for external phase verification.\n",
        "\n**Safety:** `.env` excluded. Secrets masked in contents.\n",
    ]

    sections: list[tuple[str, list[str]]] = [
        ("Documentation & Reports", DOCS),
        ("Routes", ROUTES),
        ("Middleware", collect_glob("app/Http/Middleware/*.php")),
        ("Policies", collect_glob("app/Policies/*.php")),
        ("Providers", [
            "app/Providers/AuthServiceProvider.php",
            "app/Providers/AppServiceProvider.php",
        ]),
        ("Models", MODELS),
        ("Controllers", CONTROLLERS),
        ("Services", collect_glob("app/Services/**/*.php")),
        ("Support", collect_glob("app/Support/**/*.php")),
        ("Data", collect_glob("app/Data/**/*.php")),
        ("Migrations", migration_files()),
        ("Feature/Unit Tests (keyword matched)", test_files()),
        ("Views (keyword matched)", view_files()),
        ("Frontend Dev Storefront", FRONTEND),
        ("Config", CONFIG),
    ]

    for title, files in sections:
        buf.append(f"\n\n# Section: {title}\n")
        for rel in files:
            append_file(buf, rel)

    buf.append("\n\n")
    buf.append(schema_summary())
    buf.append("\n\n")
    buf.append(search_evidence())

    OUT.write_text("".join(buf), encoding="utf-8")
    size_mb = OUT.stat().st_size / (1024 * 1024)
    print(f"Wrote {OUT} ({size_mb:.2f} MB, {len(buf)} sections)")


if __name__ == "__main__":
    main()
