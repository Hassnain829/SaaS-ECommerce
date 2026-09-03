<?php

namespace App\Services\Delivery;

use App\Models\Store;
use App\Services\Tax\TaxConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Separates "ever completed delivery onboarding" from current operational readiness.
 *
 * Operational truth remains DeliverySetupStatusService::is_ready (DR-05 / cutover).
 */
class DeliverySetupLifecycleService
{
    public const STATE_NEVER_CONFIGURED = 'never_configured';

    public const STATE_CONFIGURED_READY = 'configured_ready';

    public const STATE_CONFIGURED_NEEDS_ATTENTION = 'configured_needs_attention';

    public function __construct(
        private readonly DeliverySetupStatusService $status,
        private readonly TaxConfigurationService $taxConfiguration,
    ) {}

    public function hasCompletedSetup(Store $store): bool
    {
        return $store->delivery_setup_completed_at !== null;
    }

    /**
     * @return self::STATE_*
     */
    public function state(Store $store, ?bool $isCurrentlyReady = null): string
    {
        $ready = $isCurrentlyReady ?? $this->isCurrentlyReady($store);

        if (! $this->hasCompletedSetup($store)) {
            return self::STATE_NEVER_CONFIGURED;
        }

        return $ready
            ? self::STATE_CONFIGURED_READY
            : self::STATE_CONFIGURED_NEEDS_ATTENTION;
    }

    public function isCurrentlyReady(Store $store): bool
    {
        return (bool) ($this->assess($store)['is_ready'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function assess(Store $store): array
    {
        return $this->status->assess(
            $store,
            $store->locations()->orderByDesc('is_default')->orderBy('name')->get(),
            $store->shippingZones()->orderByDesc('is_active')->orderBy('name')->get(),
            $store->shippingMethods()->with(['shippingZone', 'carrierAccount'])->orderBy('name')->get(),
            $store->carrierAccounts()->orderBy('display_name')->get(),
            $this->taxConfiguration->settingsForStore($store),
        );
    }

    /**
     * Named route for the first incomplete first-time setup step.
     */
    public function nextIncompleteSetupRouteName(Store $store): string
    {
        $assessment = $this->assess($store);

        if (! empty($assessment['is_ready'])) {
            return 'settings.delivery.setup.review';
        }

        $errorIds = collect($assessment['blocking_items'] ?? $assessment['health_items'] ?? [])
            ->filter(fn (array $item): bool => ($item['severity'] ?? '') === 'error')
            ->pluck('id')
            ->filter()
            ->values();

        foreach ($errorIds as $id) {
            $id = (string) $id;
            if (str_starts_with($id, 'ship_from_')) {
                return 'settings.delivery.setup.ship-from';
            }
        }

        foreach ($errorIds as $id) {
            $id = (string) $id;
            if (str_starts_with($id, 'delivery_area_')) {
                return 'settings.delivery.setup.deliver-to';
            }
        }

        foreach ($errorIds as $id) {
            $id = (string) $id;
            if (str_starts_with($id, 'delivery_option_')) {
                return 'settings.delivery.setup.delivery-option';
            }
        }

        return 'settings.delivery.setup.review';
    }

    /**
     * Stamp completion when first-time wizard finish succeeds. Never clears later.
     */
    public function markCompleted(Store $store, ?Carbon $at = null): void
    {
        if ($store->delivery_setup_completed_at !== null) {
            return;
        }

        $store->forceFill([
            'delivery_setup_completed_at' => $at ?? now(),
        ])->save();
    }

    public function clearWizardSession(Request $request): void
    {
        $request->session()->forget([
            'delivery_wizard.location_id',
            'delivery_wizard.zone_id',
            'delivery_wizard.method_id',
        ]);
    }

    /**
     * Backfill stores with ready checkout OR clear legacy setup evidence
     * (ship-from + area + checkout option), even if currently broken.
     *
     * @return int Number of stores stamped
     */
    public function backfillCompletedAtForReadyStores(): int
    {
        $stamped = 0;

        Store::query()
            ->whereNull('delivery_setup_completed_at')
            ->orderBy('id')
            ->chunkById(50, function ($stores) use (&$stamped): void {
                foreach ($stores as $store) {
                    if (! $this->isCurrentlyReady($store) && ! $this->hasLegacySetupEvidence($store)) {
                        continue;
                    }

                    $this->markCompleted($store);
                    $stamped++;
                }
            });

        return $stamped;
    }

    /**
     * Evidence that the merchant previously configured delivery, even if readiness is broken now.
     */
    public function hasLegacySetupEvidence(Store $store): bool
    {
        $hasOrigin = $store->locations()
            ->where(function ($q): void {
                $q->where('is_default', true)->orWhere('fulfills_online_orders', true);
            })
            ->whereNotNull('address_line1')
            ->where('address_line1', '!=', '')
            ->exists();

        if (! $hasOrigin) {
            return false;
        }

        $hasZone = $store->shippingZones()->exists();
        $hasMethod = $store->shippingMethods()->exists();

        return $hasZone && $hasMethod;
    }
}
