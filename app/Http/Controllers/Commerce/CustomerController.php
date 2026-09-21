<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerTag;
use App\Services\CustomerMetricsService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->where(fn ($query) => $query->where('store_id', $store->id)),
            ],
            'phone' => ['nullable', 'string', 'max:80'],
        ], [
            'email.unique' => 'Another customer in this store already uses this email.',
        ]);

        $firstName = filled($validated['first_name'] ?? null) ? trim((string) $validated['first_name']) : null;
        $lastName = filled($validated['last_name'] ?? null) ? trim((string) $validated['last_name']) : null;
        $fullName = trim(collect([$firstName, $lastName])->filter()->implode(' '));

        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'email' => strtolower(trim((string) $validated['email'])),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName !== '' ? $fullName : null,
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'status' => 'active',
            'source' => 'dashboard',
        ]);

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_created',
            store: $store,
            metadata: [
                'customer_id' => $customer->id,
                'email' => $customer->email,
            ]
        );

        return redirect()
            ->route('customersProfile', $customer)
            ->with('success', 'Customer created.')
            ->with('success_title', 'Customer added')
            ->with('success_meta', 'You can update their contact details anytime from this profile.');
    }

    public function export(Request $request): StreamedResponse
    {
        $store = $request->attributes->get('currentStore');

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $tagId = (int) $request->query('tag', 0);

        $query = Customer::query()
            ->where('store_id', $store->id)
            ->with([
                'tags:id,store_id,name',
                'addresses' => fn ($addressQuery) => $addressQuery
                    ->orderByDesc('is_default')
                    ->orderBy('id'),
            ]);

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($tagId > 0) {
            $query->whereHas('tags', fn ($tagQuery) => $tagQuery
                ->where('customer_tags.store_id', $store->id)
                ->where('customer_tags.id', $tagId));
        }

        $customers = $query
            ->orderByDesc('last_order_at')
            ->orderByDesc('created_at')
            ->get();

        app(SecurityLogRecorder::class)->record(
            $request,
            'customers_exported',
            store: $store,
            metadata: [
                'row_count' => $customers->count(),
                'status' => $status,
                'tag_id' => $tagId > 0 ? $tagId : null,
                'search' => $search !== '' ? $search : null,
            ]
        );

        $filename = 'customers-'.$store->id.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($customers): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'customer_id',
                'email',
                'first_name',
                'last_name',
                'full_name',
                'phone',
                'status',
                'accepts_marketing',
                'tags',
                'total_orders',
                'total_spent',
                'average_order_value',
                'last_order_at',
                'source',
                'default_shipping_line1',
                'default_shipping_city',
                'default_shipping_state',
                'default_shipping_postal',
                'default_shipping_country',
                'created_at',
            ]);

            foreach ($customers as $customer) {
                $shipping = $customer->addresses
                    ->first(fn (CustomerAddress $address): bool => $address->type === 'shipping' && $address->is_default)
                    ?? $customer->addresses->first(fn (CustomerAddress $address): bool => $address->type === 'shipping');

                fputcsv($out, [
                    $customer->id,
                    $customer->email,
                    $customer->first_name,
                    $customer->last_name,
                    $customer->full_name,
                    $customer->phone,
                    $customer->status,
                    $customer->accepts_marketing ? 'yes' : 'no',
                    $customer->tags->pluck('name')->implode('|'),
                    $customer->total_orders,
                    $customer->total_spent,
                    $customer->average_order_value,
                    optional($customer->last_order_at)?->toIso8601String(),
                    $customer->source,
                    $shipping?->address_line1,
                    $shipping?->city,
                    $shipping?->state,
                    $shipping?->postal_code,
                    $shipping?->country_code ?: $shipping?->country,
                    optional($customer->created_at)?->toIso8601String(),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateIdentity(Request $request, Customer $customer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where(fn ($query) => $query->where('store_id', $store->id))
                    ->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:80'],
        ], [
            'email.unique' => 'Another customer in this store already uses this email.',
        ]);

        $firstName = filled($validated['first_name'] ?? null) ? trim((string) $validated['first_name']) : null;
        $lastName = filled($validated['last_name'] ?? null) ? trim((string) $validated['last_name']) : null;
        $fullName = trim(collect([$firstName, $lastName])->filter()->implode(' '));
        $email = strtolower(trim((string) $validated['email']));
        $phone = filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null;

        $previous = [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];

        $customer->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName !== '' ? $fullName : null,
            'email' => $email,
            'phone' => $phone,
        ]);

        $changed = [];
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            if ((string) ($previous[$field] ?? '') !== (string) ($customer->{$field} ?? '')) {
                $changed[] = $field;
            }
        }

        if ($changed !== []) {
            app(SecurityLogRecorder::class)->record(
                $request,
                'customer_identity_updated',
                store: $store,
                metadata: [
                    'customer_id' => $customer->id,
                    'changed' => $changed,
                    'previous_email' => $previous['email'],
                    'email' => $customer->email,
                ]
            );
        }

        return back()
            ->with('success', 'Customer contact details updated.')
            ->with('success_title', 'Customer updated')
            ->with('success_meta', 'Name, email, and phone changes apply to this customer profile going forward. Past orders keep their original snapshots.');
    }

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

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertCustomerBelongsToStore($customer, $store->id);

        $customerId = (int) $customer->id;
        $previousEmail = (string) $customer->email;

        $customer->addresses()->delete();
        $customer->profileNotes()->delete();
        $customer->tags()->detach();

        $customer->forceFill([
            'email' => 'deleted-'.$customerId.'@anonymized.invalid',
            'first_name' => null,
            'last_name' => null,
            'full_name' => 'Deleted customer',
            'phone' => null,
            'password' => null,
            'status' => 'blocked',
            'blocked_at' => now(),
            'blocked_reason' => 'Customer record deleted and anonymized.',
            'accepts_marketing' => false,
            'marketing_consent' => false,
            'marketing_consent_at' => null,
            'marketing_consent_source' => null,
            'date_of_birth' => null,
            'gender' => null,
            'notes' => null,
            'meta' => null,
        ])->save();

        $customer->delete();

        app(SecurityLogRecorder::class)->record(
            $request,
            'customer_deleted',
            store: $store,
            metadata: [
                'customer_id' => $customerId,
                'previous_email_hash' => hash('sha256', Str::lower($previousEmail)),
            ]
        );

        return redirect()
            ->route('customers')
            ->with('success', 'Customer deleted and personal details removed.')
            ->with('success_title', 'Customer removed')
            ->with('success_meta', 'Order history stays in this store with the anonymized customer link.');
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
