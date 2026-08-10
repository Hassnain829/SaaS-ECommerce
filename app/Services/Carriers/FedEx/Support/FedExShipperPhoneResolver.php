<?php

namespace App\Services\Carriers\FedEx\Support;

use App\Models\CarrierAccount;
use App\Models\Location;

/**
 * Resolves the ship-from phone FedEx requires on labels.
 *
 * Preference: location phone → FedEx connectivity / registration phone.
 * Merchants should not re-enter a phone they already provided during FedEx connect.
 */
final class FedExShipperPhoneResolver
{
    public function fromLocation(Location $origin): string
    {
        return trim((string) ($origin->phone ?? ''));
    }

    public function fromAccount(?CarrierAccount $account): string
    {
        if ($account === null) {
            return '';
        }

        $details = $account->registrationDetails();
        $phone = trim((string) ($details['phone'] ?? ''));
        if ($phone !== '') {
            return $phone;
        }

        $session = $account->registrationSession;
        if ($session === null) {
            return '';
        }

        $address = $session->registrationAddress();

        return trim((string) ($address['phone'] ?? ''));
    }

    public function resolve(Location $origin, ?CarrierAccount $account = null): string
    {
        $fromLocation = $this->fromLocation($origin);
        if ($fromLocation !== '') {
            return $fromLocation;
        }

        return $this->fromAccount($account);
    }

    /**
     * When the location has no phone but FedEx connect already captured one,
     * copy it onto the location once so Locations and fulfillment stay aligned.
     */
    public function resolveAndBackfill(Location $origin, ?CarrierAccount $account = null): string
    {
        $resolved = $this->resolve($origin, $account);
        if ($resolved === '' || filled(trim((string) ($origin->phone ?? '')))) {
            return $resolved;
        }

        $origin->forceFill(['phone' => $resolved])->save();

        return $resolved;
    }
}
