<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerTag;
use App\Services\CustomerMetricsService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function storeNote(Request $request, Customer $customer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $customer->profileNotes()->create([
            'store_id' => $store->id,
            'user_id' => $request->user()?->id,
            'body' => $validated['body'],
        ]);

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_note_added',
            store: $store,
            metadata: ['customer_id' => $customer->id]
        );

        return back()->with('success', 'Customer note added.');
    }

    public function storeTag(Request $request, Customer $customer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:32'],
        ]);

        $name = trim($validated['name']);
        $slug = Str::slug($name);

        $tag = CustomerTag::query()->firstOrCreate(
            ['store_id' => $store->id, 'slug' => $slug],
            [
                'name' => $name,
                'color' => $validated['color'] ?? null,
            ]
        );

        $customer->tags()->syncWithoutDetaching([$tag->id]);

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_tags_updated',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'tag_id' => $tag->id,
                'action' => 'attached',
            ]
        );

        return back()->with('success', 'Customer tag added.');
    }

    public function destroyTag(Request $request, Customer $customer, CustomerTag $customerTag): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);
        $this->assertTagBelongsToStore($customerTag, $store->id);

        $customer->tags()->detach($customerTag->id);

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_tags_updated',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'tag_id' => $customerTag->id,
                'action' => 'detached',
            ]
        );

        return back()->with('success', 'Customer tag removed.');
    }

    public function storeAddress(Request $request, Customer $customer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);

        $validated = $this->validatedAddress($request);

        $address = $customer->addresses()->create($validated);
        if ($address->is_default) {
            $this->clearOtherDefaults($customer, $address);
        }

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_address_changed',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'action' => 'created',
            ]
        );

        return back()->with('success', 'Customer address added.');
    }

    public function updateAddress(Request $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);
        $this->assertAddressBelongsToCustomer($address, $customer->id);

        $address->update($this->validatedAddress($request));
        if ($address->is_default) {
            $this->clearOtherDefaults($customer, $address);
        }

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_address_changed',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'action' => 'updated',
            ]
        );

        return back()->with('success', 'Customer address updated.');
    }

    public function makeDefaultAddress(Request $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);
        $this->assertAddressBelongsToCustomer($address, $customer->id);

        $address->forceFill(['is_default' => true])->save();
        $this->clearOtherDefaults($customer, $address);

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_address_changed',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'action' => 'made_default',
            ]
        );

        return back()->with('success', 'Default address updated.');
    }

    public function destroyAddress(Request $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);
        $this->assertAddressBelongsToCustomer($address, $customer->id);

        $wasDefault = (bool) $address->is_default;
        $type = $address->type;
        $addressId = $address->id;
        $address->delete();

        if ($wasDefault) {
            $replacement = $customer->addresses()
                ->where('type', $type)
                ->orderBy('id')
                ->first();

            if ($replacement) {
                $replacement->forceFill(['is_default' => true])->save();
            }
        }

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_address_changed',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'address_id' => $addressId,
                'action' => 'deleted',
            ]
        );

        return back()->with('success', 'Customer address removed.');
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'blocked'])],
            'blocked_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $previousStatus = $customer->status;
        $status = $validated['status'];

        $customer->update([
            'status' => $status,
            'blocked_at' => $status === 'blocked' ? now() : null,
            'blocked_reason' => $status === 'blocked' ? ($validated['blocked_reason'] ?? null) : null,
        ]);

        app(SecurityLogRecorder::class)->record(
            $request,
            $status === 'blocked' ? 'customer_blocked' : 'customer_unblocked',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'previous_status' => $previousStatus,
                'new_status' => $status,
            ]
        );

        return back()->with('success', $status === 'blocked' ? 'Customer blocked.' : 'Customer unblocked.');
    }

    public function updateMarketing(Request $request, Customer $customer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);

        $validated = $request->validate([
            'marketing_consent' => ['nullable', 'boolean'],
            'marketing_consent_source' => ['nullable', 'string', 'max:120'],
        ]);

        $consent = (bool) ($validated['marketing_consent'] ?? false);
        $customer->update([
            'accepts_marketing' => $consent,
            'marketing_consent' => $consent,
            'marketing_consent_at' => $consent ? now() : null,
            'marketing_consent_source' => $consent ? ($validated['marketing_consent_source'] ?? 'dashboard') : null,
        ]);

        app(SecurityLogRecorder::class)->record(
            $request,
            'marketing_consent_updated',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'marketing_consent' => $consent,
            ]
        );

        return back()->with('success', 'Marketing consent updated.');
    }

    public function recalculateMetrics(Request $request, Customer $customer, CustomerMetricsService $metrics): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);

        $metrics->recalculate($customer);

        return back()->with('success', 'Customer metrics refreshed.');
    }

    private function assertCustomerBelongsToStore(Customer $customer, int $storeId): void
    {
        if ((int) $customer->store_id !== $storeId) {
            abort(404);
        }
    }

    private function assertTagBelongsToStore(CustomerTag $tag, int $storeId): void
    {
        if ((int) $tag->store_id !== $storeId) {
            abort(404);
        }
    }

    private function assertAddressBelongsToCustomer(CustomerAddress $address, int $customerId): void
    {
        if ((int) $address->customer_id !== $customerId) {
            abort(404);
        }
    }

    private function clearOtherDefaults(Customer $customer, CustomerAddress $address): void
    {
        $customer->addresses()
            ->where('id', '!=', $address->id)
            ->where('type', $address->type)
            ->update(['is_default' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAddress(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['shipping', 'billing'])],
            'name' => ['nullable', 'string', 'max:160'],
            'company' => ['nullable', 'string', 'max:160'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'province_code' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'country' => ['required', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'phone' => ['nullable', 'string', 'max:80'],
            'delivery_instructions' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
            'is_residential' => ['nullable', 'boolean'],
        ]);
    }
}
