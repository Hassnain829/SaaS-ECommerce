<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Carrier\Validation\Concerns\ResolvesFedExValidationAccount;
use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FedExValidationExportController extends Controller
{
    use ResolvesFedExValidationAccount;

    public function diagnostic(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationEvidenceExporter $exporter,
        SecurityLogRecorder $securityLogRecorder,
    ): BinaryFileResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        $zipPath = $exporter->exportDiagnostic(
            store: $store,
            account: $account,
            region: (string) $request->query('region', 'US'),
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_validation_diagnostic_export',
            store: $store,
            metadata: ['carrier_account_id' => $account->id],
        );

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(false);
    }

    public function final(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationEvidenceExporter $exporter,
        FedExValidationPreflightService $preflight,
        SecurityLogRecorder $securityLogRecorder,
    ): BinaryFileResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;
        $assessment = $preflight->assess($store, $account);

        abort_unless($assessment['ready'] ?? false, 422, 'Final FedEx validation export is blocked until all required evidence checks pass.');

        $zipPath = $exporter->exportFinal(
            store: $store,
            account: $account,
            region: (string) $request->query('region', 'US'),
        );

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_validation_final_export',
            store: $store,
            metadata: ['carrier_account_id' => $account->id],
        );

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(false);
    }
}
