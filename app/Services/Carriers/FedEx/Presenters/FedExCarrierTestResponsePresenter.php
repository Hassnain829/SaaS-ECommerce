<?php

namespace App\Services\Carriers\FedEx\Presenters;

use App\Models\CarrierAccount;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use Illuminate\Http\RedirectResponse;

final class FedExCarrierTestResponsePresenter
{
    /**
     * @param  array<string, mixed>  $presentation
     * @param  array<string, string>  $inputSummary
     */
    public function redirectWithFedExTestResult(
        CarrierAccount $account,
        string $tool,
        string $label,
        CarrierApiResult $result,
        array $presentation,
        array $inputSummary,
        ?string $resultKind = null,
    ): RedirectResponse {
        $resultKind ??= $this->resolveResultKind($result, $tool);

        $redirect = redirect()
            ->route('shippingAutomation', ['tab' => 'carriers'])
            ->with('fedex_test_result', [
                'account_id' => $account->id,
                'tool' => $tool,
                'label' => $label,
                'success' => $resultKind === 'success',
                'result_kind' => $resultKind,
                'failure_kind' => in_array($resultKind, ['fedex_api', 'fedex_authorization_blocked'], true) ? $resultKind : null,
                'message' => $resultKind === 'success'
                    ? $this->successMessage($tool, $presentation)
                    : ($resultKind === 'warning'
                        ? $this->warningMessage($tool, $presentation)
                        : ($result->errorMessage ?? 'FedEx request failed.')),
                'support_summary' => $resultKind === 'fedex_authorization_blocked'
                    ? $this->authorizationBlockedSupportSummary($tool, $result)
                    : null,
                'input_summary' => $inputSummary,
                'presentation' => $presentation,
                'request_summary' => $result->requestSummary,
                'response_summary' => $result->responseSummary,
                'duration_ms' => $result->durationMs,
                'fedex_transaction_id' => data_get($result->responseSummary, 'fedex_transaction_id'),
            ]);

        if ($resultKind === 'success') {
            return $redirect
                ->with('success', $this->successFlashMessage($tool))
                ->with('success_title', 'FedEx validation tools');
        }

        if (in_array($resultKind, ['warning', 'fedex_authorization_blocked'], true)) {
            return $redirect
                ->with('success', $resultKind === 'fedex_authorization_blocked'
                    ? 'FedEx authorization blocked — evidence captured for FedEx support.'
                    : $this->warningFlashMessage($tool))
                ->with('success_title', 'FedEx validation tools');
        }

        if ($tool === 'service_availability') {
            return $redirect;
        }

        return $redirect
            ->withErrors(['fedex' => $result->errorMessage ?? 'FedEx request failed.'])
            ->with('error_title', 'FedEx validation tools');
    }

    public function resolveResultKind(CarrierApiResult $result, string $tool): string
    {
        if ($result->success) {
            return 'success';
        }

        if ($result->errorCode === 'fedex_authorization_blocked') {
            return 'fedex_authorization_blocked';
        }

        return $tool === 'service_availability' ? 'fedex_api' : 'failure';
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    public function addressValidationResultKind(
        CarrierApiResult $result,
        array $presentation,
    ): string {
        if (! $result->success) {
            return 'failure';
        }

        if (count($presentation['resolved_addresses'] ?? []) > 0) {
            return 'success';
        }

        return 'warning';
    }

    /**
     * @param  array<string, string|null>  $destination
     * @return array<string, string>
     */
    public function destinationInputSummary(string $originName, array $destination): array
    {
        return array_filter([
            'origin' => $originName,
            'destination_country' => $destination['country_code'] ?? null,
            'destination_state' => $destination['state'] ?? null,
            'destination_city' => $destination['city'] ?? null,
            'destination_postal' => $destination['postal_code'] ?? null,
        ]);
    }

    private function authorizationBlockedSupportSummary(string $tool, CarrierApiResult $result): string
    {
        $httpStatus = (int) data_get($result->responseSummary, 'http_status');
        $fedexCode = data_get($result->responseSummary, 'errors.0.code');
        $endpoint = data_get($result->requestSummary, 'endpoint');

        return collect([
            'FedEx authorization blocked (HTTP '.$httpStatus.')',
            'Tool: '.str($tool)->replace('_', ' ')->title(),
            'Endpoint: '.$endpoint,
            filled($fedexCode) ? 'FedEx error code: '.$fedexCode : null,
            'This is a FedEx entitlement/validation blocker — not a local payload defect.',
            'Next step: confirm API entitlement with FedEx integrator support before resubmitting validation evidence.',
        ])->filter()->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    private function warningMessage(string $tool, array $presentation): string
    {
        return match ($tool) {
            'address_validation' => collect($presentation['warnings'] ?? [])->first()
                ?? 'FedEx address check connected successfully, but no country-matching resolved address was returned.',
            default => 'FedEx test completed with warnings. Review the response details below.',
        };
    }

    private function warningFlashMessage(string $tool): string
    {
        return match ($tool) {
            'address_validation' => 'FedEx address check connected, but no country-matching suggestion was returned.',
            default => 'FedEx test completed with warnings.',
        };
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    private function successMessage(string $tool, array $presentation): string
    {
        return match ($tool) {
            'address_validation' => count($presentation['resolved_addresses'] ?? []) > 0
                ? 'FedEx returned '.count($presentation['resolved_addresses']).' country-matching resolved address suggestion(s). Review before using in production.'
                : 'FedEx address check completed. Review the response details below.',
            'service_availability' => ($presentation['service_count'] ?? 0) > 0
                ? 'FedEx returned '.($presentation['service_count']).' available service option(s) for this route.'
                : 'FedEx service availability check completed. Review the response details below.',
            'rate_quote' => ($presentation['rate_count'] ?? 0) > 0
                ? 'FedEx returned '.($presentation['rate_count']).' test rate option(s). This does not create a shipment or change checkout totals.'
                : 'FedEx rate quote check completed. Review the response details below.',
            'ship_validate' => 'FedEx ship validation passed for '.$presentation['test_case'].'. No label was created.',
            'ship_label' => ($presentation['label_saved'] ?? false)
                ? 'Sandbox label saved for evidence. Tracking and label binary are redacted in exports.'
                : 'FedEx label request completed. Review the response details below.',
            'ship_cancel' => 'FedEx cancel request completed.',
            default => 'FedEx test completed.',
        };
    }

    private function successFlashMessage(string $tool): string
    {
        return match ($tool) {
            'address_validation' => 'FedEx address check completed. This is a validation suggestion only.',
            'service_availability' => 'FedEx service availability check completed.',
            'rate_quote' => 'FedEx test rate quote completed. No shipment was created and checkout totals were not changed.',
            'ship_validate' => 'FedEx ship validation completed.',
            'ship_label' => 'FedEx sandbox label test completed.',
            'ship_cancel' => 'FedEx cancel test completed.',
            default => 'FedEx test completed.',
        };
    }
}
