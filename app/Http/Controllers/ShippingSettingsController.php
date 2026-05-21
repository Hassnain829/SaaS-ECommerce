<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShippingSettingsController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        return view('user_view.shippingAutomation', [
            'selectedStore' => $store,
            'carriers' => Carrier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'carrierAccounts' => $store->carrierAccounts()
                ->with(['carrier', 'shippingMethods'])
                ->orderByDesc('status')
                ->orderBy('display_name')
                ->get(),
            'shippingZones' => $store->shippingZones()
                ->with('shippingMethods.carrierAccount.carrier')
                ->orderByDesc('is_active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'shippingMethods' => $store->shippingMethods()
                ->with(['shippingZone', 'carrierAccount.carrier'])
                ->orderByDesc('is_active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'locations' => $store->locations()
                ->orderByDesc('is_default')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
            'connectionTypes' => CarrierAccount::CONNECTION_TYPES,
            'carrierAccountStatuses' => CarrierAccount::STATUSES,
            'rateTypes' => ShippingMethod::RATE_TYPES,
        ]);
    }

    public function storeCarrierAccount(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $request->validate([
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'display_name' => ['required', 'string', 'max:120'],
            'connection_type' => ['required', Rule::in(CarrierAccount::CONNECTION_TYPES)],
            'status' => ['required', Rule::in(CarrierAccount::STATUSES)],
            'supported_countries' => ['nullable'],
            'enabled_for_checkout' => ['nullable', 'boolean'],
        ]);

        $account = $store->carrierAccounts()->create([
            ...$validated,
            'supported_countries' => $this->listFromInput($validated['supported_countries'] ?? null),
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
            'created_by' => $request->user()?->id,
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_account_created',
            store: $store,
            metadata: ['carrier_account_id' => $account->id, 'display_name' => $account->display_name]
        );

        return back()
            ->with('success', 'Carrier account added.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateCarrierAccount(Request $request, CarrierAccount $carrierAccount, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);

        $validated = $request->validate([
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'display_name' => ['required', 'string', 'max:120'],
            'connection_type' => ['required', Rule::in(CarrierAccount::CONNECTION_TYPES)],
            'status' => ['required', Rule::in(CarrierAccount::STATUSES)],
            'supported_countries' => ['nullable'],
            'enabled_for_checkout' => ['nullable', 'boolean'],
        ]);

        $carrierAccount->update([
            ...$validated,
            'supported_countries' => $this->listFromInput($validated['supported_countries'] ?? null),
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_account_updated',
            store: $store,
            metadata: ['carrier_account_id' => $carrierAccount->id, 'display_name' => $carrierAccount->display_name]
        );

        return back()
            ->with('success', 'Carrier account updated.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function destroyCarrierAccount(Request $request, CarrierAccount $carrierAccount, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);

        $carrierAccount->delete();

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_account_deleted',
            store: $store,
            metadata: ['carrier_account_id' => $carrierAccount->id, 'display_name' => $carrierAccount->display_name]
        );

        return back()
            ->with('success', 'Carrier account removed.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function storeZone(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $this->validateZone($request);

        $zone = $store->shippingZones()->create([
            ...$validated,
            'countries' => $this->listFromInput($validated['countries'] ?? null, true),
            'regions' => $this->listFromInput($validated['regions'] ?? null),
            'postal_patterns' => $this->listFromInput($validated['postal_patterns'] ?? null),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.zone_created',
            store: $store,
            metadata: ['shipping_zone_id' => $zone->id, 'name' => $zone->name]
        );

        return back()
            ->with('success', 'Shipping zone added.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateZone(Request $request, ShippingZone $shippingZone, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingZone->store_id === (int) $store->id, 404);

        $validated = $this->validateZone($request);

        $shippingZone->update([
            ...$validated,
            'countries' => $this->listFromInput($validated['countries'] ?? null, true),
            'regions' => $this->listFromInput($validated['regions'] ?? null),
            'postal_patterns' => $this->listFromInput($validated['postal_patterns'] ?? null),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.zone_updated',
            store: $store,
            metadata: ['shipping_zone_id' => $shippingZone->id, 'name' => $shippingZone->name]
        );

        return back()
            ->with('success', 'Shipping zone updated.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function destroyZone(Request $request, ShippingZone $shippingZone, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingZone->store_id === (int) $store->id, 404);

        $shippingZone->delete();

        $securityLogRecorder->record(
            $request,
            'shipping.zone_deleted',
            store: $store,
            metadata: ['shipping_zone_id' => $shippingZone->id, 'name' => $shippingZone->name]
        );

        return back()
            ->with('success', 'Shipping zone removed.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function storeMethod(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $this->validateMethod($request, $store->id);

        $method = $store->shippingMethods()->create([
            ...$validated,
            'code' => $this->uniqueMethodCode($store->id, $validated['name']),
            'carrier_account_id' => $validated['carrier_account_id'] ?? null,
            'flat_rate' => $validated['flat_rate'] ?? 0,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.method_created',
            store: $store,
            metadata: ['shipping_method_id' => $method->id, 'name' => $method->name]
        );

        return back()
            ->with('success', 'Delivery method added.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateMethod(Request $request, ShippingMethod $shippingMethod, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingMethod->store_id === (int) $store->id, 404);

        $validated = $this->validateMethod($request, $store->id);

        $shippingMethod->update([
            ...$validated,
            'carrier_account_id' => $validated['carrier_account_id'] ?? null,
            'flat_rate' => $validated['flat_rate'] ?? 0,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.method_updated',
            store: $store,
            metadata: ['shipping_method_id' => $shippingMethod->id, 'name' => $shippingMethod->name]
        );

        return back()
            ->with('success', 'Delivery method updated.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function destroyMethod(Request $request, ShippingMethod $shippingMethod, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingMethod->store_id === (int) $store->id, 404);

        $shippingMethod->delete();

        $securityLogRecorder->record(
            $request,
            'shipping.method_deleted',
            store: $store,
            metadata: ['shipping_method_id' => $shippingMethod->id, 'name' => $shippingMethod->name]
        );

        return back()
            ->with('success', 'Delivery method removed.')
            ->with('success_title', 'Shipping & delivery');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateZone(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'countries' => ['nullable'],
            'regions' => ['nullable'],
            'postal_patterns' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMethod(Request $request, int $storeId): array
    {
        return $request->validate([
            'shipping_zone_id' => [
                'required',
                'integer',
                Rule::exists('shipping_zones', 'id')->where('store_id', $storeId),
            ],
            'carrier_account_id' => [
                'nullable',
                'integer',
                Rule::exists('carrier_accounts', 'id')->where('store_id', $storeId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'delivery_speed_label' => ['nullable', 'string', 'max:120'],
            'rate_type' => ['required', Rule::in(ShippingMethod::RATE_TYPES)],
            'flat_rate' => ['nullable', 'numeric', 'min:0'],
            'free_over_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['nullable', 'numeric', 'min:0'],
            'estimated_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'estimated_max_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'enabled_for_checkout' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /**
     * @return list<string>|null
     */
    private function listFromInput(mixed $value, bool $uppercase = false): ?array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        }

        $parts = collect($parts)
            ->map(fn ($part): string => trim((string) $part))
            ->filter()
            ->map(fn ($part): string => $uppercase ? strtoupper($part) : $part)
            ->unique()
            ->values()
            ->all();

        return $parts === [] ? null : $parts;
    }

    private function uniqueMethodCode(int $storeId, string $name): string
    {
        $base = Str::slug($name) ?: 'delivery-method';
        $code = $base;
        $counter = 2;

        while (ShippingMethod::query()->where('store_id', $storeId)->where('code', $code)->exists()) {
            $code = $base.'-'.$counter;
            $counter++;
        }

        return $code;
    }
}
