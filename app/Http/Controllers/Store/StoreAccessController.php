<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Billing\StoreSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreAccessController extends Controller
{
    public function expired(Request $request, StoreSubscriptionService $subscriptions): View
    {
        $store = $request->attributes->get('currentStore');
        $subscription = $store ? $subscriptions->subscriptionFor($store) : null;

        if ($subscription) {
            $subscriptions->refreshStoreStatus($subscription);
            $subscription->refresh();
            $subscription->loadMissing('package');
        }

        return view('user_view.store_access_expired', [
            'store' => $store,
            'subscription' => $subscription,
        ]);
    }
}
