<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Carrier\Validation\Concerns\ResolvesFedExValidationAccount;
use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\Capabilities\FedExCapabilityRegistry;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExBrandComplianceService;
use App\Services\Carriers\FedEx\Validation\FedExCapabilityEvidenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FedExValidationCapabilitiesController extends Controller
{
    use ResolvesFedExValidationAccount;

    public function show(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExBrandComplianceService $brandCompliance,
        FedExCapabilityEvidenceService $capabilityEvidence,
    ): View {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $evidenceMode = $request->boolean('evidence_mode');

        return view('user_view.shipping.fedex_capabilities', [
            'selectedStore' => $account->store,
            'account' => $account,
            'evidenceMode' => $evidenceMode,
            'brandStatus' => $brandCompliance->workspaceStatus(),
            'legalNotice' => $brandCompliance->legalNotice(),
            'customerCapabilities' => $capabilityEvidence->customerFacingCapabilities(),
            'validationCapabilities' => $capabilityEvidence->validationCapabilities(),
            'registryVersion' => FedExCapabilityRegistry::VERSION,
            'logoHash' => $brandCompliance->logoHash(),
            'capturedAt' => now()->toIso8601String(),
        ]);
    }
}
