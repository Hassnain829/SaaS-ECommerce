<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Services\Billing\StoreSubscriptionService;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(StoreSubscriptionService $subscriptions): View
    {
        $subscriptions->refreshExpiredStatuses();

        $totalStores = Store::query()->count();
        $activeTrials = StoreSubscription::query()
            ->where('status', StoreSubscription::STATUS_TRIAL)
            ->count();
        $expired = StoreSubscription::query()
            ->where('status', StoreSubscription::STATUS_EXPIRED)
            ->count();
        $suspended = StoreSubscription::query()
            ->where('status', StoreSubscription::STATUS_SUSPENDED)
            ->count();
        $endingSoon = StoreSubscription::query()
            ->whereIn('status', [StoreSubscription::STATUS_TRIAL, StoreSubscription::STATUS_ACTIVE])
            ->whereNotNull('access_ends_at')
            ->whereBetween('access_ends_at', [now(), now()->addDays(7)])
            ->count();
        $activeAccess = StoreSubscription::query()
            ->whereIn('status', [StoreSubscription::STATUS_TRIAL, StoreSubscription::STATUS_ACTIVE])
            ->count();
        $ungatedStores = max(0, $totalStores - StoreSubscription::query()->count());

        return view('admin_view.dashboard', [
            'totalStores' => $totalStores,
            'activeTrials' => $activeTrials,
            'expired' => $expired,
            'suspended' => $suspended,
            'endingSoon' => $endingSoon,
            'activeAccess' => $activeAccess,
            'ungatedStores' => $ungatedStores,
        ]);
    }
}
