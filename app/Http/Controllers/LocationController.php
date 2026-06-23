<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Inventory\DefaultLocationService;
use App\Services\SecurityLogRecorder;
use App\Support\StorePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(
        private readonly CarrierOriginReadinessService $originReadiness,
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

        return view('user_view.locations', [
            'selectedStore' => $store,
            'locations' => $locations,
            'locationTypes' => Location::TYPES,
            'canManageLocations' => $request->user()?->hasStorePermission($store, StorePermission::SETTINGS_MANAGE) ?? false,
            'originReadinessByLocationId' => $originReadinessByLocationId,
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

        return back()
            ->with('success', 'Location added.')
            ->with('success_title', 'Inventory location');
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

        return back()
            ->with('success', 'Location updated.')
            ->with('success_title', 'Inventory location');
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

        return back()
            ->with('success', "{$location->name} is now the default inventory location.")
            ->with('success_title', 'Default location changed');
    }

    public function deactivate(Request $request, Location $location): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $location->store_id === (int) $store->id, 404);

        $activeCount = $store->locations()->where('is_active', true)->count();
        if ($activeCount <= 1 && $location->is_active) {
            return back()->withErrors([
                'location' => 'Keep at least one active inventory location for this store.',
            ]);
        }

        if ($location->is_default && $location->is_active) {
            return back()->withErrors([
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

        return back()
            ->with('success', $location->is_active ? 'Location activated.' : 'Location deactivated.')
            ->with('success_title', 'Inventory location');
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
            'pickup_enabled' => ['nullable', 'boolean'],
            'routing_priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'service_countries' => ['nullable', 'string', 'max:1000'],
            'service_regions' => ['nullable', 'string', 'max:1000'],
            'service_postal_patterns' => ['nullable', 'string', 'max:1000'],
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

        $validated['fulfills_online_orders'] = $request->has('fulfills_online_orders')
            ? $request->boolean('fulfills_online_orders')
            : true;
        $validated['pickup_enabled'] = $request->boolean('pickup_enabled');
        $validated['routing_priority'] = (int) ($validated['routing_priority'] ?? 100);
        $validated['service_countries'] = $this->normalizeCountries($request->input('service_countries'));
        $validated['service_regions'] = $this->normalizeList($request->input('service_regions'));
        $validated['service_postal_patterns'] = $this->normalizeList($request->input('service_postal_patterns'), preserveWildcard: true);

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

    /**
     * @return list<string>|null
     */
    private function normalizeCountries(mixed $value): ?array
    {
        $countries = collect($this->normalizeList($value))
            ->map(fn (string $country): string => $this->originReadiness->normalizeCountryCode($country) ?? '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $countries === [] ? null : $countries;
    }

    /**
     * @return list<string>|null
     */
    private function normalizeList(mixed $value, bool $preserveWildcard = false): ?array
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $items = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        $normalized = collect($items)
            ->map(fn ($item): string => strtoupper(trim((string) $item))
            )
            ->map(fn (string $item): string => $preserveWildcard ? str_replace(' ', '', $item) : $item)
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }
}
