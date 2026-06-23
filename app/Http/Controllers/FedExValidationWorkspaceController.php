<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesFedExValidationAccount;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Services\Carriers\FedEx\FedExConfig;
use App\Services\Carriers\FedEx\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\FedExValidationScopeService;
use App\Services\Carriers\FedEx\FedExValidationStatusPresenter;
use App\Services\Carriers\FedEx\FedExValidationWorkspaceCardPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FedExValidationWorkspaceController extends Controller
{
    use ResolvesFedExValidationAccount;

    public function show(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationPreflightService $preflight,
        FedExValidationStatusPresenter $statusPresenter,
        FedExValidationScopeService $scopeService,
        FedExValidationWorkspaceCardPresenter $cardPresenter,
        FedExShipTestCaseFixtureService $fixtureService,
    ): View {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        $assessment = $preflight->assess($store, $account);

        return view('user_view.fedex_validation.workspace', [
            'selectedStore' => $store,
            'account' => $account,
            'preflight' => $assessment,
            'capabilityMatrix' => $statusPresenter->capabilityMatrix($store, $account),
            'requiredScopes' => $scopeService->resolveRequiredScopes(),
            'lockedShipScenarios' => \App\Services\Carriers\FedEx\FedExValidationScenarioCatalog::lockedShipScenarios(),
            'checksByKey' => collect($assessment['checks'] ?? [])->keyBy('key'),
            'trackingNumbers' => $this->trackingNumbersFromShipEvents($store, $account),
            'trackingConfigured' => filled($config->basicIntegratedVisibilityPath()),
            'shipCancelRequired' => $scopeService->shipCancelRequired(),
            'tradeDocumentsRequired' => $scopeService->tradeDocumentsRequired(),
            'tradeDocumentsConfigured' => filled($config->tradeDocumentsUploadPath()),
            'validationCards' => $cardPresenter->cards($store, $account, $assessment),
            'invoiceEndpointConfigured' => $config->mfaInvoiceValidationPath() !== null,
            'mfaInvoicePrefill' => $fixtureService->mfaInvoice(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function trackingNumbersFromShipEvents(\App\Models\Store $store, CarrierAccount $account): array
    {
        return CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->where('status', CarrierApiEvent::STATUS_SUCCEEDED)
            ->whereBetween('http_status', [200, 299])
            ->orderByDesc('id')
            ->get()
            ->flatMap(function (CarrierApiEvent $event): array {
                $numbers = [];
                $body = $event->response_body_encrypted ?? [];

                foreach ((array) data_get($body, 'output.transactionShipments', []) as $shipment) {
                    if (is_string($master = data_get($shipment, 'masterTrackingNumber')) && $master !== '') {
                        $numbers[] = $master;
                    }

                    foreach ((array) data_get($shipment, 'pieceResponses', []) as $piece) {
                        if (is_string($tracking = data_get($piece, 'trackingNumber')) && $tracking !== '') {
                            $numbers[] = $tracking;
                        }
                    }
                }

                return $numbers;
            })
            ->unique()
            ->values()
            ->all();
    }
}
