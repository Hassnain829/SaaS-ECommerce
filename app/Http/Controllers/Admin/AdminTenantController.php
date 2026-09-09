<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasPackage;
use App\Models\Store;
use App\Services\Billing\StoreSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTenantController extends Controller
{
    public function __construct(
        protected StoreSubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): View
    {
        $this->subscriptions->refreshExpiredStatuses();

        $search = trim((string) $request->query('q', ''));

        $stores = Store::query()
            ->with(['subscription.package', 'user'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin_view.tenants.index', [
            'stores' => $stores,
            'search' => $search,
        ]);
    }

    public function show(Store $store): View
    {
        $store->load(['subscription.package', 'subscription.assignedBy', 'user', 'members']);

        if ($store->subscription) {
            $this->subscriptions->refreshStoreStatus($store->subscription);
            $store->load('subscription.package');
        }

        $packages = SaasPackage::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin_view.tenants.show', [
            'store' => $store,
            'subscription' => $store->subscription,
            'packages' => $packages,
        ]);
    }

    public function updateAccess(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['grant_trial', 'assign_package', 'suspend', 'reactivate'])],
            'package_id' => ['nullable', 'integer', 'exists:saas_packages,id'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $package = isset($data['package_id'])
            ? SaasPackage::query()->find($data['package_id'])
            : null;
        $admin = $request->user();
        $notes = $data['notes'] ?? null;

        match ($data['action']) {
            'grant_trial' => $this->subscriptions->grantTrial(
                $store,
                (int) ($data['trial_days'] ?? 14),
                $package,
                $admin,
                $notes,
            ),
            'assign_package' => $this->subscriptions->assignPackage(
                $store,
                $package,
                $admin,
                $notes,
            ),
            'suspend' => $this->subscriptions->suspend($store, $admin, $notes),
            'reactivate' => $this->subscriptions->reactivate(
                $store,
                $admin,
                $notes,
                isset($data['trial_days']) ? (int) $data['trial_days'] : null,
                $package,
            ),
        };

        return redirect()
            ->route('admin-tenant.show', $store)
            ->with('success', 'Store access updated.');
    }
}
