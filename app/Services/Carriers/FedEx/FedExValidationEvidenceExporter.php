<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use Illuminate\Support\Facades\File;
use ZipArchive;

class FedExValidationEvidenceExporter
{
    /**
     * @var list<string>
     */
    private const EXPORT_ACTIONS = [
        CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
        CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
        CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
        CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY,
        CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
        CarrierApiEvent::ACTION_FEDEX_SHIP_VALIDATE,
        CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
        CarrierApiEvent::ACTION_FEDEX_SHIP_CANCEL,
    ];

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExValidationStatusPresenter $statusPresenter,
        private readonly FedExShipTestCaseFixtureService $shipFixtures,
    ) {
    }

    public function export(
        Store $store,
        ?CarrierAccount $account = null,
        ?CarrierAccountRegistrationSession $session = null,
        string $region = 'US',
        ?string $environment = null,
    ): string {
        $environment = $this->config->environment($environment ?? $account?->environment ?? CarrierAccount::ENVIRONMENT_SANDBOX);
        $timestamp = now()->format('Ymd_His');
        $bundleDir = storage_path("app/fedex-validation/{$store->id}/{$timestamp}/fedex-validation");

        File::ensureDirectoryExists($bundleDir.'/registration');
        File::ensureDirectoryExists($bundleDir.'/api-events');
        File::ensureDirectoryExists($bundleDir.'/labels');
        File::ensureDirectoryExists($bundleDir.'/notes');

        $session ??= $account?->latestRegistrationSession;

        $this->writeFile($bundleDir.'/README.md', $this->readme($store, $account, $environment, $region));
        $this->writeFile($bundleDir.'/environment-summary.json', json_encode($this->environmentSummary($account, $environment), JSON_PRETTY_PRINT));
        $this->writeFile(
            $bundleDir.'/registration/redacted-registration-session.json',
            json_encode($this->redactedSession($session), JSON_PRETTY_PRINT),
        );
        $this->writeFile(
            $bundleDir.'/test-case-summary.json',
            json_encode($this->testCaseSummary($region, $account, $store), JSON_PRETTY_PRINT),
        );
        $this->writeFile($bundleDir.'/screenshots-required-checklist.md', $this->screenshotsChecklist());
        $this->writeFile($bundleDir.'/notes/rate-quote-blocker.md', $this->rateQuoteBlockerNote($store, $account));
        $this->exportApiEvents($bundleDir.'/api-events', $store, $account);
        $this->exportLabels($bundleDir.'/labels', $store, $account);

        $zipFilename = "fedex-validation-bundle-{$store->id}-{$timestamp}.zip";
        $zipPath = storage_path("app/fedex-validation/{$store->id}/{$timestamp}/{$zipFilename}");
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (File::allFiles($bundleDir) as $file) {
            $zip->addFile($file->getPathname(), 'fedex-validation/'.str_replace('\\', '/', $file->getRelativePathname()));
        }

        $zip->close();

        return $zipPath;
    }

    private function writeFile(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    private function readme(Store $store, ?CarrierAccount $account, string $environment, string $region): string
    {
        return implode("\n", [
            '# FedEx Integrator Validation Evidence Bundle',
            '',
            'Store: '.$store->name.' (#'.$store->id.')',
            'Environment: '.$environment,
            'Region: '.$region,
            'Carrier account: '.($account ? $account->maskedAccountNumber() : 'n/a'),
            'Connection model: '.($account?->connection_model ?? 'n/a'),
            'Credentials mode: '.($account?->usesFedExIntegratorProvider() ? 'integrator_child' : 'merchant_developer'),
            '',
            'This bundle contains redacted JSON and optional label files only.',
            'Excluded by design: secrets, OAuth tokens, child keys/passwords, PINs, full account numbers, raw label base64, source code, .env, vendor, node_modules.',
            '',
            'Generated at: '.now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentSummary(?CarrierAccount $account, string $environment): array
    {
        return [
            'environment' => $environment,
            'base_url' => $this->config->baseUrl($environment),
            'registration_path' => $this->config->registrationPath($environment),
            'address_validation_path' => $this->config->addressValidationPath(),
            'service_availability_path' => $this->config->serviceAvailabilityPath(),
            'rate_quote_path' => $this->config->rateQuotePath(),
            'ship_validate_path' => $this->config->shipValidatePath($environment),
            'ship_create_path' => $this->config->shipCreatePath($environment),
            'ship_cancel_path' => $this->config->shipCancelPath($environment),
            'model_a_enabled' => $this->config->modelAEnabled(),
            'production_enabled' => $this->config->productionEnabled(),
            'ship_sandbox_label_generation_enabled' => $this->config->shipSandboxLabelGenerationEnabled(),
            'ship_evidence_enabled' => $this->config->shipEvidenceEnabled(),
            'parent_client_configured' => $this->config->isConfigured($environment),
            'parent_client_id_last4' => $this->last4($this->config->parentClientId($environment) ?? ''),
            'account_last4' => $account ? $this->last4((string) $account->provider_account_number) : null,
            'credentials_mode' => $account?->usesFedExIntegratorProvider() ? 'integrator_child' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function redactedSession(?CarrierAccountRegistrationSession $session): array
    {
        if ($session === null) {
            return ['session' => null];
        }

        return [
            'id' => $session->id,
            'status' => $session->status,
            'environment' => $session->environment,
            'account_last4' => $session->account_last4,
            'account_name_length' => strlen((string) $session->account_name),
            'eula_version' => $session->eula_version,
            'eula_accepted_at' => $session->eula_accepted_at?->toIso8601String(),
            'fedex_transaction_id' => $session->fedex_transaction_id,
            'mfa_method' => $session->mfa_method,
            'request_summary' => $session->request_summary_json,
            'response_summary' => $session->response_summary_json,
            'last_error_code' => $session->last_error_code,
            'last_error_message' => $session->last_error_message,
        ];
    }

    private function exportApiEvents(string $directory, Store $store, ?CarrierAccount $account): void
    {
        foreach (self::EXPORT_ACTIONS as $action) {
            $event = $this->latestEvent($store, $account, $action);

            $payload = [
                'action' => $action,
                'event' => $event === null ? null : $this->redactedEvent($event),
                'note' => $event === null
                    ? 'No recorded event for this action yet. Run the corresponding validation tool before submission.'
                    : null,
            ];

            $this->writeFile($directory.'/'.$action.'.json', json_encode($payload, JSON_PRETTY_PRINT));
        }
    }

    private function exportLabels(string $directory, Store $store, ?CarrierAccount $account): void
    {
        $query = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('artifact_type', 'like', 'ship_label_%');

        if ($account !== null) {
            $query->where('carrier_account_id', $account->id);
        }

        $artifacts = $query->orderBy('id')->get();

        if ($artifacts->isEmpty()) {
            $this->writeFile($directory.'/../labels-not-generated.md', implode("\n", [
                '# Labels not generated',
                '',
                'No sandbox label artifacts were saved yet.',
                '',
                'If Ship API is blocked with HTTP 403, include the api-events/fedex_ship_create_label.json file and rate-quote-blocker.md in your FedEx support request.',
            ]));

            return;
        }

        $manifest = [];

        foreach ($artifacts as $artifact) {
            $sourcePath = $artifact->file_path ? storage_path('app/'.$artifact->file_path) : null;
            $targetName = basename((string) $artifact->file_path);

            if ($sourcePath && File::exists($sourcePath)) {
                File::copy($sourcePath, $directory.'/'.$targetName);
            }

            $manifest[] = [
                'artifact_type' => $artifact->artifact_type,
                'label' => $artifact->label,
                'file' => $targetName,
                'fedex_transaction_id' => $artifact->fedex_transaction_id,
                'request_summary' => $artifact->request_summary_json,
                'response_summary' => $artifact->response_summary_json,
                'created_at' => $artifact->created_at?->toIso8601String(),
            ];
        }

        $this->writeFile($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, mixed>
     */
    private function redactedEvent(CarrierApiEvent $event): array
    {
        return [
            'action' => $event->action,
            'status' => $event->status,
            'environment' => $event->environment,
            'request_id' => $event->request_id,
            'error_message' => $event->error_message,
            'request_summary' => $event->request_summary,
            'response_summary' => $event->response_summary,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    private function latestEvent(Store $store, ?CarrierAccount $account, string $action): ?CarrierApiEvent
    {
        $query = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('action', $action)
            ->latest('id');

        if ($account !== null) {
            $query->where('carrier_account_id', $account->id);
        }

        return $query->first();
    }

    private function rateQuoteBlockerNote(Store $store, ?CarrierAccount $account): string
    {
        if ($account === null) {
            return '# Rate quote blocker\n\nNo carrier account selected.';
        }

        $status = $this->statusPresenter->capabilityMatrix($store, $account)['rate_quote'];

        return implode("\n", [
            '# FedEx rate quote authorization blocker',
            '',
            'Status: '.($status['label'] ?? 'unknown'),
            'HTTP status: '.($status['http_status'] ?? 'n/a'),
            '',
            ($status['detail'] ?? 'FedEx sandbox child credentials returned HTTP 403 FORBIDDEN for Comprehensive Rates.'),
            '',
            'This is treated as a FedEx entitlement/validation blocker — not a local payload defect.',
            'Include api-events/fedex_rate_quote.json and this note when contacting FedEx integrator support.',
        ]);
    }

    private function screenshotsChecklist(): string
    {
        return implode("\n", [
            '# Screenshots required for FedEx integrator validation',
            '',
            '- [ ] FedEx EULA acceptance screen',
            '- [ ] Account registration form',
            '- [ ] MFA method selection (if applicable)',
            '- [ ] Successful connection summary with Integrator Provider badge',
            '- [ ] Address validation result (US-only suggestions visible)',
            '- [ ] Service availability result',
            '- [ ] Rate quote result showing FedEx authorization blocked (HTTP 403) OR successful quote',
            '- [ ] Ship validate result',
            '- [ ] Sandbox label PDF/PNG/ZPL result or authorization blocked message',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function testCaseSummary(string $region, ?CarrierAccount $account, Store $store): array
    {
        $capabilities = $account
            ? $this->statusPresenter->capabilityMatrix($store, $account)
            : [];

        return [
            'region' => strtoupper($region),
            'registration_baseline' => app(FedExTestCaseFixtureService::class)->fixtures(),
            'ship_test_cases' => $this->shipFixtures->fixtures(),
            'capability_matrix' => $capabilities,
            'note' => 'Derived from recorded CarrierApiEvent rows and saved label artifacts — no placeholder JSON.',
        ];
    }

    private function last4(string $value): ?string
    {
        return strlen($value) >= 4 ? substr($value, -4) : null;
    }
}
