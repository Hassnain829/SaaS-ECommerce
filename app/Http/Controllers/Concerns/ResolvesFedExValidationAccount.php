<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\FedEx\FedExConfig;
use Illuminate\Http\Request;

trait ResolvesFedExValidationAccount
{
    protected function resolveFedExValidationAccount(Request $request, CarrierAccount $carrierAccount, FedExConfig $config): CarrierAccount
    {
        abort_unless($config->validationModeEnabled(), 403, 'FedEx validation mode is not enabled for this environment.');

        $store = $request->attributes->get('currentStore');
        abort_unless($store instanceof Store, 404);
        abort_unless((int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);
        abort_unless($carrierAccount->usesFedExIntegratorProvider(), 404);
        abort_unless($carrierAccount->canUseFedExApiChecks(), 404);

        return $carrierAccount->load('store');
    }
}
