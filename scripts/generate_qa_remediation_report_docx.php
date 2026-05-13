<?php

/**
 * Builds a minimal OOXML Word document (no external dependencies).
 * Run: php scripts/generate_qa_remediation_report_docx.php
 */

declare(strict_types=1);

$outPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'QA_REMEDIATION_REPORT.docx';

$sections = [
    ['h', 'QA remediation report — SaaS E-Commerce'],
    ['p', 'Generated for project: SaaS E-Commerce (Laravel 12 merchant platform). This summarizes security, release hygiene, CI, SSRF hardening, API throttling, tests, documentation, and admin view renames completed in the remediation pass.'],
    ['h', '1. Executive summary'],
    ['p', 'Critical and high-priority QA items were addressed incrementally: .gitignore and env templates, SSRF protection for remote product images, GitHub Actions CI, named API rate limits for developer storefront routes, route registration fix for /api/developer-storefront/*, PHPUnit and feature tests, project README and supporting docs, safe removal of committed .env.testing in favor of phpunit.xml + .env.testing.example, and renaming two admin Blade files for spelling and safe filenames.'],
    ['h', '2. Release hygiene and secrets'],
    ['p', '.gitignore updated: no longer ignores .env.example; ignores .env.* with exceptions for example templates; vendor, node_modules (root and dev-test-storefront), bootstrap/cache/*.php, database/*.sqlite, .phpunit.cache, storage/logs/*.log, public/build, etc.'],
    ['p', 'Added root .env.example and dev-test-storefront/.env.example (placeholders only). Added .env.testing.example. Removed tracked .env.testing from workspace; APP_KEY and testing DB come from phpunit.xml.'],
    ['p', 'Added SECURITY_ROTATION_REQUIRED.md listing manual rotation for APP_KEY, Stripe keys/webhooks, developer storefront tokens, mail/AWS/Slack credentials, and DB passwords if ever exposed.'],
    ['h', '3. SSRF hardening (ProductCatalogImageDownloader)'],
    ['p', 'New class App\\Support\\Security\\ServerSideImageHttpUrlValidator: http/https only, no URL credentials, localhost blocked, DNS resolution with every resolved IP required to be publicly routable (FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE).'],
    ['p', 'Downloader: validates URL before Http::get; connectTimeout(8), timeout(25), allow_redirects false, body size cap, Content-Type must start with image/.'],
    ['p', 'Tests: tests/Unit/ServerSideImageHttpUrlValidatorTest.php; tests/Feature/ProductCatalogImageDownloaderTest.php. Import/job tests use https://1.1.1.1/... with Http::fake so DNS matches public IPs.'],
    ['h', '4. CI/CD'],
    ['p', '.github/workflows/ci.yml: checkout, forbidden-path checks, PHP 8.3 + extensions (dom, mbstring, xml, xmlwriter, sqlite, etc.), composer install + validate, php -l, Pint --test if present, php artisan test, Node 22, npm ci + npm run build at root and dev-test-storefront.'],
    ['h', '5. API throttling'],
    ['p', 'AppServiceProvider registers RateLimiter keys: api-dev-catalog, api-dev-orders, api-dev-checkout, api-dev-external (per store id when resolved, else IP).'],
    ['p', 'routes/api.php: throttles applied to developer storefront and v1 groups. Stripe webhook routes intentionally not throttled (signature verification; see docs/SECURITY_HARDENING.md).'],
    ['h', '6. Routing fix (developer storefront)'],
    ['p', 'Inner routes use relative paths catalog and orders (no leading slash) inside prefix developer-storefront so URLs are GET /api/developer-storefront/catalog and POST /api/developer-storefront/orders, matching tests and dev-test-storefront defaultApiBase.'],
    ['h', '7. Documentation'],
    ['p', 'README.md replaced with project overview, stack, PHP/Node requirements, setup, tests, security/release notes. Added docs/LOCAL_SETUP.md, docs/RELEASE_CHECKLIST.md, docs/SECURITY_HARDENING.md, docs/REFACTORING_ROADMAP.md.'],
    ['h', '8. Admin Blade renames'],
    ['p', 'admin_infrastucture_add_logistic.blade.php -> admin_infrastructure_add_logistic.blade.php. admin_settings_security&Auth.blade.php -> admin_settings_security_auth.blade.php. AdminController view references updated.'],
    ['h', '9. Validation commands'],
    ['p', 'composer validate --strict: PASSED.'],
    ['p', 'php artisan test: PASSED (full suite after route fix).'],
    ['p', 'npm ci / npm run build (root and dev-test-storefront): may FAIL on Windows with EPERM if node native binaries are locked (e.g. npm run dev running); stop dev servers and retry. CI is the reference for green builds.'],
    ['h', '10. Manual actions still required'],
    ['p', 'Follow SECURITY_ROTATION_REQUIRED.md if real secrets were ever in a ZIP or repo.'],
    ['p', 'Ensure git does not track vendor, node_modules, .env, bootstrap/cache/*.php, database/*.sqlite — see docs/RELEASE_CHECKLIST.md.'],
    ['p', 'Stop Vite/npm processes before npm ci on Windows if EPERM occurs.'],
    ['h', '11. Suggested next steps'],
    ['p', 'Re-run npm ci and npm run build locally after closing dev servers. Optionally install pandoc for future markdown→docx exports. Continue larger refactors per docs/REFACTORING_ROADMAP.md in small PRs.'],
];

function xmlText(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function buildDocumentBody(array $sections): string
{
    $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body>';

    foreach ($sections as $row) {
        [$type, $text] = $row;
        if ($type === 'h') {
            $xml .= '<w:p><w:pPr><w:spacing w:before="240" w:after="120"/></w:pPr>'
                .'<w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t>'.xmlText($text).'</w:t></w:r></w:p>';
        } else {
            $xml .= '<w:p><w:r><w:t>'.xmlText($text).'</w:t></w:r></w:p>';
        }
    }

    $xml .= '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>'
        .'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/>'
        .'</w:sectPr></w:body></w:document>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$xml;
}

$contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$rels = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$documentXml = buildDocumentBody($sections);

$zip = new ZipArchive;
if ($zip->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not open zip for writing: {$outPath}\n");
    exit(1);
}

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('word/document.xml', $documentXml);
$zip->close();

echo "Wrote: {$outPath}\n";
