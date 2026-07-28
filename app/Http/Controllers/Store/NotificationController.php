<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Notifications\NotificationQueryService;
use App\Support\NotificationEvent;
use App\Support\StorePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationQueryService $query,
        private readonly NotificationPreferenceService $preferences,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function index(Request $request): View
    {
        $store = $this->requireCurrentStore($request);
        $user = $request->user();

        $category = $request->string('category')->toString() ?: null;
        if ($category !== null && ! array_key_exists($category, NotificationEvent::uiGroups())) {
            $category = null;
        }

        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'type' => $request->string('type')->toString() ?: null,
            'category' => $category,
            'q' => $request->string('q')->toString() ?: null,
        ];

        $paginator = $this->query->paginateForUser($store, $user, array_filter($filters), 40);
        $groups = $this->query->groupByRecency(collect($paginator->items()));
        $prefs = $this->preferences->pairForUser($store, $user);
        $failedEmails = $this->query->failedEmailsForUser($store, $user);
        $canManageCustomerEmailFailures = $this->canManageCustomerEmailFailures($request, $store);
        $failedCustomerEmails = $canManageCustomerEmailFailures
            ? $this->query->failedCustomerEmailsForStore($store)
            : collect();
        $unreadCount = $this->query->unreadCount($store, $user);
        $eventTypes = $prefs[NotificationEvent::CHANNEL_IN_APP]->event_types
            ?? NotificationEvent::defaultEventTypes();

        $groupEnabled = [];
        foreach (NotificationEvent::uiGroups() as $key => $group) {
            $groupEnabled[$key] = collect($group['events'])->contains(
                fn (string $event): bool => (bool) ($eventTypes[$event] ?? false)
            );
        }

        return view('user_view.notifications', [
            'store' => $store,
            'notifications' => $paginator,
            'groups' => $groups,
            'filters' => $filters,
            'unreadCount' => $unreadCount,
            'failedEmails' => $failedEmails,
            'failedCustomerEmails' => $failedCustomerEmails,
            'canManageCustomerEmailFailures' => $canManageCustomerEmailFailures,
            'preferences' => $prefs,
            'eventLabels' => NotificationEvent::labels(),
            'eventTypes' => $eventTypes,
            'uiGroups' => NotificationEvent::uiGroups(),
            'groupEnabled' => $groupEnabled,
            'inAppEnabled' => (bool) $prefs[NotificationEvent::CHANNEL_IN_APP]->is_enabled,
            'emailEnabled' => (bool) $prefs[NotificationEvent::CHANNEL_EMAIL]->is_enabled,
            'settingsSaved' => $request->session()->get('success') === 'Notification settings saved.',
        ]);
    }

    public function markRead(Request $request, StoreNotification $notification): RedirectResponse
    {
        $store = $this->requireCurrentStore($request);
        $row = $this->query->findForUser($store, $request->user(), (int) $notification->id);
        $row->markRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $store = $this->requireCurrentStore($request);
        $this->query->markAllRead($store, $request->user());

        return back()->with('success', 'All notifications marked as read.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $store = $this->requireCurrentStore($request);
        $user = $request->user();

        $request->validate([
            'in_app_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'groups' => ['sometimes', 'array'],
        ]);

        $eventTypes = NotificationEvent::defaultEventTypes();
        foreach (NotificationEvent::uiGroups() as $key => $group) {
            $enabled = $request->boolean('groups.'.$key);
            foreach ($group['events'] as $event) {
                $eventTypes[$event] = $enabled;
            }
        }

        $this->preferences->updateForUser($store, $user, [
            'in_app_enabled' => $request->boolean('in_app_enabled'),
            'email_enabled' => $request->boolean('email_enabled'),
            'event_types' => $eventTypes,
        ], $user);

        return back()->with('success', 'Notification settings saved.');
    }

    public function retry(Request $request, StoreNotification $notification): RedirectResponse
    {
        $store = $this->requireCurrentStore($request);
        $user = $request->user();

        $row = StoreNotification::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->whereKey($notification->id)
            ->firstOrFail();

        if (! $this->dispatcher->retryEmail($row)) {
            return back()->withErrors(['notification' => 'This email cannot be retried.']);
        }

        return back()->with('success', 'Email delivery queued again.');
    }

    public function retryCustomer(Request $request, StoreNotification $notification): RedirectResponse
    {
        $store = $this->requireCurrentStore($request);
        abort_unless($this->canManageCustomerEmailFailures($request, $store), 403);

        $row = $this->query->findFailedCustomerEmail($store, (int) $notification->id);

        if (! $this->dispatcher->retryEmail($row)) {
            return back()->withErrors(['notification' => 'This customer email cannot be retried.']);
        }

        return back()->with('success', 'Customer email queued again.');
    }

    private function canManageCustomerEmailFailures(Request $request, Store $store): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $role = $store->roleForUser($user);
        if (in_array($role, [Store::ROLE_OWNER, Store::ROLE_MANAGER], true)) {
            return true;
        }

        return $user->hasStorePermission($store, StorePermission::ORDERS_MANAGE);
    }

    private function requireCurrentStore(Request $request): Store
    {
        $store = $request->attributes->get('currentStore');
        if (! $store instanceof Store) {
            abort(404);
        }

        return $store;
    }
}
