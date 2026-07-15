<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\FedEx\Capabilities\FedExCapabilityRegistry;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExBrandComplianceService;
use App\Services\Carriers\FedEx\Validation\FedExCapabilityEvidenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FedExValidationCapabilitiesController extends Controller
{
    public function show(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExBrandComplianceService $brandCompliance,
        FedExCapabilityEvidenceService $capabilityEvidence,
        FedExCapabilityRegistry $registry,
    ): View {
        $evidenceMode = $request->boolean('evidence_mode');
        $account = $this->resolveFedExCapabilitiesAccount($request, $carrierAccount, $config, $evidenceMode);

        return view('user_view.shipping.fedex_capabilities', [
            'selectedStore' => $account->store,
            'account' => $account,
            'evidenceMode' => $evidenceMode,
            'brandStatus' => $brandCompliance->workspaceStatus(),
            'legalNotice' => $brandCompliance->legalNotice(),
            'customerCapabilities' => $capabilityEvidence->customerFacingCapabilities(),
            'brandingEvidenceNames' => $registry->brandingEvidenceDisplayNames(),
            'validationCapabilities' => $evidenceMode ? null : $capabilityEvidence->validationCapabilities(),
            'registryVersion' => FedExCapabilityRegistry::VERSION,
            'logoHash' => $brandCompliance->logoHash(),
            'capturedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Branding / capability disclosure does not require live FedEx API credentials —
     * only store ownership, FedEx provider, and (for evidence mode) validation mode.
     */
    private function resolveFedExCapabilitiesAccount(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        bool $evidenceMode,
    ): CarrierAccount {
        if ($evidenceMode) {
            abort_unless(
                $config->validationModeEnabled(),
                403,
                'FedEx validation evidence mode is not enabled for this environment.',
            );
        }

        abort_unless($config->validationModeEnabled(), 403, 'FedEx validation mode is not enabled for this environment.');

        $store = $request->attributes->get('currentStore');
        abort_unless($store instanceof Store, 404);
        abort_unless((int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);
        abort_unless($carrierAccount->usesFedExIntegratorProvider(), 404);

        return $carrierAccount->load('store');
    }
}
