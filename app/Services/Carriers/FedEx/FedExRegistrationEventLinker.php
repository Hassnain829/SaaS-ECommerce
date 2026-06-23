<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;

class FedExRegistrationEventLinker
{
    public function linkSessionEventsToAccount(
        CarrierAccount $account,
        CarrierAccountRegistrationSession $session,
    ): int {
        abort_unless((int) $account->store_id === (int) $session->store_id, 422);
        abort_unless((int) $session->id === (int) ($account->registration_session_id ?? 0)
            || (int) $session->carrier_account_id === (int) $account->id, 422);

        return CarrierApiEvent::query()
            ->where('store_id', $account->store_id)
            ->where('registration_session_id', $session->id)
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->whereNull('carrier_account_id')
            ->update(['carrier_account_id' => $account->id]);
    }
}
