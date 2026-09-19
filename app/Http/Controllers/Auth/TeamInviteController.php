<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityLogRecorder;
use App\Services\Settings\StoreMemberInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class TeamInviteController extends Controller
{
    public function __construct(private readonly StoreMemberInvitationService $invitations) {}

    public function show(Request $request, User $user): View
    {
        if (! $request->hasValidSignature()) {
            return view('auth.team-invite', [
                'invitee' => null,
                'stores' => collect(),
                'expired' => true,
                'alreadyAccepted' => false,
                'needsPassword' => false,
                'acceptUrl' => null,
                'signedInAsOther' => false,
            ]);
        }

        $storeIds = StoreMemberInvitationService::decodeStoreIds($request->query('stores'));
        $stores = $this->invitations->pendingStores($user, $storeIds);
        $alreadyAccepted = $stores->isEmpty();
        $expiresAt = $this->signatureExpiry($request);

        return view('auth.team-invite', [
            'invitee' => $user,
            'stores' => $stores,
            'expired' => false,
            'alreadyAccepted' => $alreadyAccepted,
            'needsPassword' => (bool) $user->must_set_password,
            'acceptUrl' => $alreadyAccepted ? null : URL::temporarySignedRoute(
                'team-invites.store',
                $expiresAt,
                [
                    'user' => $user->id,
                    'stores' => (string) $request->query('stores', ''),
                ]
            ),
            'signedInAsOther' => $request->user() && (int) $request->user()->id !== (int) $user->id,
        ]);
    }

    public function store(Request $request, User $user, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $storeIds = StoreMemberInvitationService::decodeStoreIds($request->query('stores'));
        $pending = $this->invitations->pendingStores($user, $storeIds);

        if ($pending->isEmpty()) {
            return redirect()
                ->route('signin')
                ->with('status', 'This invitation has already been accepted. Sign in to continue.');
        }

        $validated = $request->validate(
            $user->must_set_password
                ? ['password' => ['required', 'string', 'confirmed', 'min:8']]
                : []
        );

        $acceptedStores = $this->invitations->accept(
            $user,
            $validated['password'] ?? null,
            $storeIds,
        );

        if ($request->user() && (int) $request->user()->id !== (int) $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::login($user->fresh());
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'is_active' => true])->save();

        $homeStore = $acceptedStores->first();
        if ($homeStore) {
            $request->session()->put('current_store_id', $homeStore->id);
        }

        foreach ($acceptedStores as $store) {
            $securityLogRecorder->record(
                $request,
                'team_member_joined',
                store: $store,
                user: $user,
                metadata: ['store_id' => $store->id]
            );
        }

        $storeLabel = $this->invitations->storeLabel($acceptedStores);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Welcome to '.$storeLabel.'. Your store access is ready.')
            ->with('success_title', 'Invitation accepted');
    }

    private function signatureExpiry(Request $request): Carbon
    {
        $expires = (int) $request->query('expires');

        if ($expires > time()) {
            return Carbon::createFromTimestamp($expires);
        }

        return now()->addDays(7);
    }
}
