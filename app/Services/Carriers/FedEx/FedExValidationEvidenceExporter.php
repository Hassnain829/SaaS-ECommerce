<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use Illuminate\Support\Facades\File;
use ZipArchive;

class FedExValidationEvidenceExporter
{
    public function __construct(
        private readonly FedExConfig $config,
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
        $baseDir = storage_path("app/fedex-validation/{$store->id}/{$timestamp}");
        $bundleDir = $baseDir.'/fedex-validation';
        File::ensureDirectoryExists($bundleDir.'/json');
        File::ensureDirectoryExists($bundleDir.'/labels');
        File::ensureDirectoryExists($bundleDir.'/notes');

        $this->writeFile($bundleDir.'/README.md', $this->readme($store, $account, $environment, $region));
        $this->writeFile($bundleDir.'/environment-summary.json', json_encode($this->environmentSummary($environment), JSON_PRETTY_PRINT));
        $this->writeFile($bundleDir.'/redacted-registration-session.json', json_encode($this->redactedSession($session), JSON_PRETTY_PRINT));
        $this->writeFile($bundleDir.'/redacted-api-events.json', json_encode($this->redactedApiEvents($store, $account), JSON_PRETTY_PRINT));
        $this->writeFile($bundleDir.'/screenshots-required-checklist.md', $this->screenshotsChecklist());
        $this->writeFile($bundleDir.'/test-case-summary.json', json_encode($this->testCaseSummary($region), JSON_PRETTY_PRINT));

        foreach ([
            'registration-factor1.json' => ['step' => 'factor1'],
            'registration-pin-email.json' => ['step' => 'pin_email'],
            'registration-pin-sms.json' => ['step' => 'pin_sms'],
            'registration-pin-call.json' => ['step' => 'pin_call'],
            'registration-invoice.json' => ['step' => 'invoice'],
            'rate-test-case.json' => ['api' => 'rate_quote'],
            'service-availability-test-case.json' => ['api' => 'service_availability'],
            'track-test-case.json' => ['api' => 'track'],
            'ship-label-pdf.json' => ['artifact' => 'label_pdf'],
            'ship-label-png.json' => ['artifact' => 'label_png'],
            'ship-label-zpl.json' => ['artifact' => 'label_zpl'],
        ] as $filename => $meta) {
            $this->writeFile(
                $bundleDir.'/json/'.$filename,
                json_encode(array_merge($meta, ['status' => 'placeholder', 'note' => 'Populate after running the corresponding FedEx validation transaction.']), JSON_PRETTY_PRINT),
            );
        }

        $zipPath = $baseDir.'/fedex-validation-bundle.zip';
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
            '',
            'This bundle contains redacted JSON only. Secrets, tokens, full account numbers, and label binaries are excluded.',
            '',
            'Generated at: '.now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentSummary(string $environment): array
    {
        return [
            'environment' => $environment,
            'base_url' => $this->config->baseUrl($environment),
            'registration_path' => $this->config->registrationPath($environment),
            'model_a_enabled' => $this->config->modelAEnabled(),
            'production_enabled' => $this->config->productionEnabled(),
            'parent_client_configured' => $this->config->isConfigured($environment),
            'parent_client_id_last4' => $this->last4($this->config->parentClientId($environment) ?? ''),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function redactedApiEvents(Store $store, ?CarrierAccount $account): array
    {
        $query = CarrierApiEvent::query()->where('store_id', $store->id)->latest('id')->limit(50);
        if ($account !== null) {
            $query->where('carrier_account_id', $account->id);
        }

        return $query->get()->map(static fn (CarrierApiEvent $event): array => [
            'action' => $event->action,
            'status' => $event->status,
            'environment' => $event->environment,
            'request_id' => $event->request_id,
            'request_summary' => $event->request_summary,
            'response_summary' => $event->response_summary,
            'created_at' => $event->created_at?->toIso8601String(),
        ])->all();
    }

    private function screenshotsChecklist(): string
    {
        return implode("\n", [
            '# Screenshots required for FedEx integrator validation',
            '',
            '- [ ] FedEx EULA acceptance screen',
            '- [ ] Account registration form',
            '- [ ] MFA method selection (if applicable)',
            '- [ ] Successful connection summary',
            '- [ ] Address validation result',
            '- [ ] Service availability result',
            '- [ ] Rate quote result (or authorization message if 403)',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function testCaseSummary(string $region): array
    {
        return [
            'region' => strtoupper($region),
            'baseline' => app(FedExTestCaseFixtureService::class)->fixtures(),
            'note' => 'Use FedEx Integrator Test Case Baseline XLSX for full transaction matrix.',
        ];
    }

    private function last4(string $value): ?string
    {
        return strlen($value) >= 4 ? substr($value, -4) : null;
    }
}
