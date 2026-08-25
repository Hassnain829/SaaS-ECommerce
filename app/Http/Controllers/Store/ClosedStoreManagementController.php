<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\SecurityLogRecorder;
use App\Services\Store\StorePurgeEligibilityService;
use App\Services\Store\StorePurgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class ClosedStoreManagementController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('store-management', [], 302)->withFragment('closed-stores');
    }

    public function restore(Request $request, int $storeId): RedirectResponse
    {
        $store = Store::onlyTrashed()->whereKey($storeId)->firstOrFail();
        $this->authorizeClosedStoreOwner($request, $store);

        $storeName = (string) $store->name;

        DB::transaction(function () use ($request, $store, $storeId, $storeName): void {
            $store->restore();

            $audit = app(SecurityLogRecorder::class)->record(
                $request,
                'store_restored',
                store: $store,
                metadata: [
                    'store_id' => (int) $storeId,
                    'store_name' => $storeName,
                    'restoration_mode' => 'soft_delete_restore',
                ]
            );

            if ($audit === null) {
                throw new RuntimeException('Store restore aborted: failed to write store_restored audit log.');
            }
        });

        return redirect()
            ->route('store-management')
            ->with('success', "Store '{$storeName}' has been restored and is available again.")
            ->with('success_title', 'Store restored')
            ->with('success_meta', 'Active store list refreshed');
    }

    public function permanentDestroy(
        Request $request,
        int $storeId,
        StorePurgeEligibilityService $eligibility,
        StorePurgeService $purgeService,
    ): RedirectResponse {
        $store = Store::onlyTrashed()->whereKey($storeId)->firstOrFail();
        $this->authorizeClosedStoreOwner($request, $store);

        $validated = $request->validate([
            'confirm_store_name' => [
                'required',
                'string',
                Rule::in([(string) $store->name]),
            ],
        ], [
            'confirm_store_name.in' => 'Type the exact store name to confirm permanent deletion.',
        ]);

        unset($validated);

        $storeName = (string) $store->name;

        try {
            $eligibility->assertEligibleForMerchantPurge($store);
            $purgeService->purge($store, $request->user());
        } catch (RuntimeException $e) {
            return redirect()
                ->route('store-management', [], 302)
                ->withFragment('closed-stores')
                ->with('error', 'Permanent deletion could not be completed. The closed store and its data were kept intact.')
                ->with('error_meta', $e->getMessage());
        }

        if ((int) $request->session()->get('onboarding_store_id') === (int) $storeId) {
            $request->session()->forget([
                'onboarding_store_draft',
                'onboarding_store_id',
                'onboarding_last_store_id',
                'onboarding_product_draft',
                'onboarding_product_id',
                'onboarding_last_product_id',
            ]);
        }

        if ((int) $request->session()->get('current_store_id') === (int) $storeId) {
            $request->session()->forget('current_store_id');
        }

        return redirect()
            ->route('store-management')
            ->with('success', "Store '{$storeName}' and its retained data were permanently deleted.")
            ->with('success_title', 'Store permanently deleted')
            ->with('success_meta', 'This action cannot be undone');
    }

    private function authorizeClosedStoreOwner(Request $request, Store $store): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $isOwner = DB::table('store_user')
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('role', Store::ROLE_OWNER)
            ->exists();

        abort_unless($isOwner, 403);
    }
}
