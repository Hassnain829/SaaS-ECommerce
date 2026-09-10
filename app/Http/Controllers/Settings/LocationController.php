<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\FedEx\Operations\FedExOperationGuard;
use App\Services\Carriers\FedEx\Support\FedExShipperPhoneResolver;
use App\Services\Inventory\DefaultLocationService;
use App\Services\SecurityLogRecorder;
use App\Support\StorePermission;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(
        private readonly CarrierOriginReadinessService $originReadiness,
        private readonly FedExOperationGuard $fedExGuard,
        private readonly FedExShipperPhoneResolver $shipperPhoneResolver,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        app(DefaultLocationService::class)->ensureFromStoreDefaults($store, $request->user());

        $locations = $store->locations()
            ->withCount('inventoryLevels')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $originReadinessByLocationId = $locations
            ->mapWithKeys(fn (Location $location): array => [
                $location->id => $this->originReadiness->assess($location),
            ])
            ->all();

        $fedExAccount = $this->fedExGuard->resolveActiveModelAAccount($store);
        $fedExConnectPhone = $this->shipperPhoneResolver->fromAccount($fedExAccount);

        $selectedLocationId = (int) $request->integer('location');
        if (! $locations->contains(fn (Location $location): bool => (int) $location->id === $selectedLocationId)) {
            $selectedLocationId = (int) ($locations->first()?->id ?? 0);
        }

        $activeLocations = $locations->where('is_active', true);
        $locationMetrics = [
            'total' => $locations->count(),
            'active' => $activeLocations->count(),
            'ship_from_ready' => $activeLocations
                ->filter(fn (Location $location): bool => (bool) ($originReadinessByLocationId[$location->id]?->ready ?? false))
                ->count(),
        ];

        return view('user_view.locations', [
            'selectedStore' => $store,
            'locations' => $locations,
            'selectedLocationId' => $selectedLocationId,
            'locationMetrics' => $locationMetrics,
            'locationTypes' => Location::TYPES,
            'countries' => TaxCountryCatalog::all(),
            'canManageLocations' => $request->user()?->hasStorePermission($store, StorePermission::SETTINGS_MANAGE) ?? false,
            'originReadinessByLocationId' => $originReadinessByLocationId,
            'fedExConnectPhone' => $fedExConnectPhone,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $this->validateLocation($request);

        $location = $store->locations()->create([
            ...$validated,
            'is_default' => false,
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        if ($store->locations()->where('is_default', true)->doesntExist()) {
            app(DefaultLocationService::class)->makeDefault($location, $request->user());
        }

        app(SecurityLogRecorder::class)->record(
            $request,
            'location_created',
            store: $store,
            metadata: ['location_id' => $location->id, 'location_name' => $location->name]
        );

        return $this->workspaceRedirect($location, 'Location added.', 'Inventory location');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $location->store_id === (int) $store->id, 404);

        $validated = $this->validateLocation($request, $location);
        $location->update([
            ...$validated,
            'updated_by' => $request->user()?->id,
        ]);

        app(SecurityLogRecorder::class)->record(
            $request,
            'location_updated',
            store: $store,
            metadata: ['location_id' => $location->id, 'location_name' => $location->name]
        );

        return $this->workspaceRedirect($location, 'Location updated.', 'Inventory location');
    }

    public function makeDefault(Request $request, Location $location): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $location->store_id === (int) $store->id, 404);

        app(DefaultLocationService::class)->makeDefault($location, $request->user());

        app(SecurityLogRecorder::class)->record(
            $request,
            'location_default_changed',
            store: $store,
            metadata: ['location_id' => $location->id, 'location_name' => $location->name]
        );

        return $this->workspaceRedirect(
            $location,
            "{$location->name} is now the default inventory location.",
            'Default location changed',
        );
    }

    public function deactivate(Request $request, Location $location): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $location->store_id === (int) $store->id, 404);

        $activeCount = $store->locations()->where('is_active', true)->count();
        if ($activeCount <= 1 && $location->is_active) {
            return redirect()
                ->route('settings.locations.index', ['location' => $location->id])
                ->withErrors([
                    'location' => 'Keep at least one active inventory location for this store.',
                ]);
        }

        if ($location->is_default && $location->is_active) {
            return redirect()
                ->route('settings.locations.index', ['location' => $location->id])
                ->withErrors([
                    'location' => 'Choose another default location before deactivating this one.',
                ]);
        }

        $location->update([
            'is_active' => ! $location->is_active,
            'updated_by' => $request->user()?->id,
        ]);

        app(SecurityLogRecorder::class)->record(
            $request,
            $location->is_active ? 'location_activated' : 'location_deactivated',
            store: $store,
            metadata: ['location_id' => $location->id, 'location_name' => $location->name]
        );

        return $this->workspaceRedirect(
            $location,
            $location->is_active ? 'Location activated.' : 'Location deactivated.',
            'Inventory location',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLocation(Request $request, ?Location $existing = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(Location::TYPES)],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'country_code' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:60'],
            'fulfills_online_orders' => ['nullable', 'boolean'],
            'routing_priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $rawCountry = filled($validated['country_code'] ?? null)
            ? trim((string) $validated['country_code'])
            : null;

        if ($rawCountry !== null) {
            $normalizedCountry = $this->originReadiness->normalizeCountryCode($rawCountry);

            if ($normalizedCountry === null || in_array($normalizedCountry, ['UN', 'XX', 'ZZ'], true)) {
                throw ValidationException::withMessages([
                    'country_code' => 'Country code must be a 2-letter country code like US.',
                ]);
            }

            $validated['country_code'] = $normalizedCountry;
        } else {
            $validated['country_code'] = null;
        }

        if (filled($validated['state'] ?? null) && filled($validated['country_code'] ?? null)) {
            $validated['state'] = $this->normalizeStateCode(
                (string) $validated['state'],
                (string) $validated['country_code'],
            );
        }

        $validated['fulfills_online_orders'] = $request->has('fulfills_online_orders')
            ? $request->boolean('fulfills_online_orders')
            : true;
        $validated['pickup_enabled'] = false;
        $validated['routing_priority'] = (int) ($validated['routing_priority'] ?? 100);

        if ($validated['fulfills_online_orders']) {
            $fulfillmentMissing = collect([
                'address_line1' => 'Address line 1',
                'city' => 'City',
                'country_code' => 'Country code',
            ])->filter(fn (string $label, string $field): bool => ! filled($validated[$field] ?? null));

            if ($fulfillmentMissing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'address_line1' => 'Online fulfillment locations need a complete ship-from address (street, city, and country code).',
                ]);
            }
        }

        $candidateAttributes = array_merge(
            $existing?->only([
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'country_code',
            ]) ?? [],
            array_intersect_key($validated, array_flip([
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'country_code',
            ])),
        );

        if ($existing !== null && $this->originReadiness->locationIsCarrierDefaultOrigin($existing)) {
            $readiness = $this->originReadiness->assessAttributes($candidateAttributes);

            if (! $readiness->ready) {
                throw ValidationException::withMessages([
                    'address_line1' => $readiness->merchantMessage.' Update the ship-from address or choose a different default origin on Shipping & Delivery.',
                ]);
            }
        }

        return $validated;
    }

    private function normalizeStateCode(string $state, string $countryCode): string
    {
        $token = strtoupper(trim($state));
        if ($token === '') {
            return '';
        }

        $catalog = TaxCountryCatalog::regionsFor($countryCode);
        if (isset($catalog[$token])) {
            return $token;
        }

        foreach ($catalog as $code => $label) {
            if (strtoupper($label) === $token) {
                return $code;
            }
        }

        return $token;
    }

    private function workspaceRedirect(Location $location, string $success, string $title): RedirectResponse
    {
        return redirect()
            ->route('settings.locations.index', ['location' => $location->id])
            ->with('success', $success)
            ->with('success_title', $title);
    }
}
