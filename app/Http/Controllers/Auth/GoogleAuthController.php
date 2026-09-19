<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleSignInService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleSignInService $googleSignIn,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->googleSignIn->isEnabled()) {
            return redirect()
                ->route('signin')
                ->withErrors(['email' => 'Google sign-in is not available yet.']);
        }

        $request->session()->put('google_auth_from', $request->headers->get('referer', ''));

        return Socialite::driver('google')
            ->redirectUrl($this->callbackUrl())
            ->scopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->googleSignIn->isEnabled()) {
            return redirect()
                ->route('signin')
                ->withErrors(['email' => 'Google sign-in is not available yet.']);
        }

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->callbackUrl())
                ->user();
            $result = $this->googleSignIn->resolveUser($googleUser);
        } catch (RuntimeException $exception) {
            return $this->failed($request, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed($request, 'Google sign-in could not be completed. Try again.');
        }

        /** @var User $user */
        $user = $result['user'];

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        app(SecurityLogRecorder::class)->record(
            $request,
            $result['created'] ? 'account_registered' : 'login',
            user: $user,
            metadata: ['provider' => 'google'],
        );

        if ($user->role?->name === 'admin') {
            return redirect()->intended(route('admin-dashboard'));
        }

        if ($result['created'] && ! $user->memberStores()->exists()) {
            return redirect()->route('onboarding-StoreDetails-1');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function callbackUrl(): string
    {
        $this->googleSignIn->hydrateRuntimeConfig();

        return rtrim((string) config('services.google.redirect'), '/');
    }

    private function failed(Request $request, string $message): RedirectResponse
    {
        $from = (string) $request->session()->pull('google_auth_from', '');
        $target = str_contains($from, '/register') ? 'register' : 'signin';

        return redirect()
            ->route($target)
            ->withErrors(['email' => $message])
            ->withInput();
    }
}
