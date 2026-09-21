<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\SecurityLogRecorder;
use App\Services\Settings\StoreMemberInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TeamInviteController extends Controller
{
    public function __construct(private readonly StoreMemberInvitationService $invitations) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitations = $this->invitations->findActiveByToken($token);
        $invitee = $invitations->first()?->user ?? $this->invitations->findInviteeByToken($token);

        if ($invitations->isEmpty()) {
            $accepted = TeamInvitation::query()
                ->where('token_hash', $this->invitations->hashToken($token))
                ->whereNotNull('accepted_at')
                ->exists();

            return view('auth.team-invite', $this->viewData(
                invitee: $invitee,
                stores: collect(),
                expired: ! $accepted,
                alreadyAccepted: $accepted,
                needsPassword: false,
                acceptUrl: null,
                token: $token,
                requiresSignIn: false,
                signedInAsOther: false,
                signedInAsInvitee: false,
            ));
        }

        $needsPassword = (bool) $invitee?->must_set_password;
        $authUser = $request->user();
        $signedInAsInvitee = $authUser && $invitee && (int) $authUser->id === (int) $invitee->id;
        $signedInAsOther = $authUser && $invitee && (int) $authUser->id !== (int) $invitee->id;
        $requiresSignIn = ! $needsPassword && ! $authUser;

        if ($requiresSignIn) {
            $request->session()->put(
                'url.intended',
                route('team-invites.show', ['token' => $token])
            );
        }

        $stores = $invitations->map(fn (TeamInvitation $invitation) => $invitation->store)->filter()->values();

        return view('auth.team-invite', $this->viewData(
            invitee: $invitee,
            stores: $stores,
            expired: false,
            alreadyAccepted: false,
            needsPassword: $needsPassword,
            acceptUrl: $requiresSignIn || $signedInAsOther
                ? null
                : route('team-invites.store', ['token' => $token]),
            token: $token,
            requiresSignIn: $requiresSignIn,
            signedInAsOther: $signedInAsOther,
            signedInAsInvitee: $signedInAsInvitee,
        ));
    }

    public function store(Request $request, string $token, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $invitations = $this->invitations->findActiveByToken($token);
        $invitee = $invitations->first()?->user;

        if (! $invitee || $invitations->isEmpty()) {
            return redirect()
                ->route('signin')
                ->with('status', 'This invitation is no longer valid. Ask the store owner to send a new one.');
        }

        $needsPassword = (bool) $invitee->must_set_password;

        if ($needsPassword) {
            if ($request->user() && (int) $request->user()->id !== (int) $invitee->id) {
                return redirect()
                    ->route('team-invites.show', ['token' => $token])
                    ->withErrors(['email' => 'Sign out first, then use this invitation to set up '.$invitee->email.'.']);
            }
        } else {
            if (! $request->user()) {
                return redirect()
                    ->guest(route('signin'))
                    ->with('status', 'Sign in as '.$invitee->email.' to accept this store invitation.');
            }

            if ((int) $request->user()->id !== (int) $invitee->id) {
                return redirect()
                    ->route('team-invites.show', ['token' => $token])
                    ->withErrors(['email' => 'Sign in as '.$invitee->email.' before accepting this invitation.']);
            }
        }

        $validated = $request->validate(
            $needsPassword
                ? ['password' => ['required', 'string', 'confirmed', 'min:8']]
                : []
        );

        $acceptedStores = $this->invitations->acceptByToken(
            $invitee,
            $token,
            $validated['password'] ?? null,
        );

        if ($acceptedStores->isEmpty()) {
            return redirect()
                ->route('signin')
                ->with('status', 'This invitation has already been accepted. Sign in to continue.');
        }

        if ($needsPassword) {
            Auth::login($invitee->fresh());
            $request->session()->regenerate();
            $invitee->forceFill(['last_login_at' => now(), 'is_active' => true])->save();
        }

        $homeStore = $acceptedStores->first();
        if ($homeStore) {
            $request->session()->put('current_store_id', $homeStore->id);
        }

        foreach ($acceptedStores as $store) {
            $securityLogRecorder->record(
                $request,
                'team_member_joined',
                store: $store,
                user: $invitee,
                metadata: ['store_id' => $store->id]
            );
        }

        $storeLabel = $this->invitations->storeLabel($acceptedStores);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Welcome to '.$storeLabel.'. Your store access is ready.')
            ->with('success_title', 'Invitation accepted');
    }

    /**
     * @param  Collection<int, Store>  $stores
     * @return array<string, mixed>
     */
    private function viewData(
        ?User $invitee,
        $stores,
        bool $expired,
        bool $alreadyAccepted,
        bool $needsPassword,
        ?string $acceptUrl,
        string $token,
        bool $requiresSignIn,
        bool $signedInAsOther,
        bool $signedInAsInvitee,
    ): array {
        return [
            'invitee' => $invitee,
            'stores' => $stores,
            'expired' => $expired,
            'alreadyAccepted' => $alreadyAccepted,
            'needsPassword' => $needsPassword,
            'acceptUrl' => $acceptUrl,
            'token' => $token,
            'requiresSignIn' => $requiresSignIn,
            'signedInAsOther' => $signedInAsOther,
            'signedInAsInvitee' => $signedInAsInvitee,
            'signinUrl' => route('signin'),
        ];
    }
}
