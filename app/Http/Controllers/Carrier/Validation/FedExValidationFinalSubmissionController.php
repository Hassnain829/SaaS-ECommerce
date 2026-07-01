<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Carrier\Validation\Concerns\ResolvesFedExValidationAccount;
use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\FedExValidationSubmissionSnapshot;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExFinalSubmissionService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FedExValidationFinalSubmissionController extends Controller
{
    use ResolvesFedExValidationAccount;

    public function finalPreflight(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExFinalSubmissionService $finalSubmission,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $assessment = $finalSubmission->runFinalPreflight($account->store, $account);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with(
                ($assessment['ready'] ?? false) ? 'success' : 'error',
                ($assessment['ready'] ?? false)
                    ? 'Final submission preflight passed — you may create a snapshot.'
                    : 'Final submission preflight blocked — resolve remaining items first.',
            )
            ->with('final_preflight', $assessment);
    }

    public function createSnapshot(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExFinalSubmissionService $finalSubmission,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $validated = $request->validate([
            'case_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $snapshot = $finalSubmission->createSnapshot(
            store: $account->store,
            account: $account,
            actor: $request->user(),
            caseReference: $validated['case_reference'] ?? null,
        );

        $securityLogRecorder->record($request, 'shipping.fedex_validation_final_snapshot', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'snapshot_id' => $snapshot->id,
        ]);

        return redirect()
            ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
            ->with('success', 'Final submission snapshot #'.$snapshot->id.' is ready for export.');
    }

    public function exportSnapshot(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExValidationSubmissionSnapshot $snapshot,
        FedExConfig $config,
        FedExFinalSubmissionService $finalSubmission,
        SecurityLogRecorder $securityLogRecorder,
    ): BinaryFileResponse|RedirectResponse {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        abort_unless((int) $snapshot->store_id === (int) $account->store_id, 404);
        abort_unless((int) $snapshot->carrier_account_id === (int) $account->id, 404);

        try {
            $zipPath = $finalSubmission->exportSnapshot($snapshot);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return redirect()
                ->route('settings.shipping.carrier-accounts.fedex.validation', $account)
                ->with('error', $exception->getMessage());
        }

        $securityLogRecorder->record($request, 'shipping.fedex_validation_final_export', store: $account->store, metadata: [
            'carrier_account_id' => $account->id,
            'snapshot_id' => $snapshot->id,
        ]);

        return response()->download($zipPath, basename($zipPath));
    }
}
