<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

class GoogleSignInService
{
    public function isEnabled(): bool
    {
        $this->hydrateRuntimeConfig();

        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * Re-read Google OAuth values from the process environment when cached
     * config was built without them (common after a cPanel deploy).
     */
    public function hydrateRuntimeConfig(): void
    {
        $id = trim((string) config('services.google.client_id'));
        $secret = trim((string) config('services.google.client_secret'));
        $redirect = trim((string) config('services.google.redirect'));

        if ($id === '') {
            $id = $this->environmentValue('GOOGLE_CLIENT_ID');
        }

        if ($secret === '') {
            $secret = $this->environmentValue('GOOGLE_CLIENT_SECRET');
        }

        $explicitRedirect = $this->environmentValue('GOOGLE_REDIRECT_URI');
        if ($explicitRedirect !== '') {
            $redirect = rtrim($explicitRedirect, '/');
        } elseif ($redirect === '') {
            $appUrl = trim((string) (config('app.url') ?: $this->environmentValue('APP_URL')));
            $redirect = rtrim($appUrl, '/').'/auth/google/callback';
        }

        config([
            'services.google.client_id' => $id,
            'services.google.client_secret' => $secret,
            'services.google.redirect' => $redirect,
        ]);
    }

    private function environmentValue(string $key): string
    {
        if (array_key_exists($key, $_ENV) && is_string($_ENV[$key])) {
            return trim($_ENV[$key]);
        }

        if (array_key_exists($key, $_SERVER) && is_string($_SERVER[$key])) {
            return trim($_SERVER[$key]);
        }

        $value = getenv($key);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array{user: User, created: bool}
     */
    public function resolveUser(SocialiteUser $googleUser): array
    {
        $googleId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $name = trim((string) $googleUser->getName());

        if ($googleId === '' || $email === '') {
            throw new RuntimeException('Google did not return a usable account email.');
        }

        if (! $this->googleEmailIsVerified($googleUser)) {
            throw new RuntimeException('That Google account email is not verified. Verify it with Google, or create an account with email and password.');
        }

        $user = User::query()->where('google_id', $googleId)->first()
            ?? User::query()->where('email', $email)->first();

        if ($user) {
            if ($user->is_active === false) {
                throw new RuntimeException('This account is deactivated. Contact the store owner before signing in again.');
            }

            $updates = [];
            if ($user->google_id !== $googleId) {
                $updates['google_id'] = $googleId;
            }
            if ($user->email_verified_at === null) {
                $updates['email_verified_at'] = now();
            }
            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }

            return ['user' => $user->fresh() ?? $user, 'created' => false];
        }

        $userRole = Role::query()->where('name', 'user')->first();
        if (! $userRole) {
            abort(500, 'The default user role is missing. Please seed roles before registering users.');
        }

        $user = User::query()->create([
            'name' => $name !== '' ? $name : Str::before($email, '@'),
            'email' => $email,
            'google_id' => $googleId,
            'password' => Str::password(32),
            'role_id' => $userRole->id,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return ['user' => $user->fresh() ?? $user, 'created' => true];
    }

    private function googleEmailIsVerified(SocialiteUser $googleUser): bool
    {
        $raw = [];
        if (method_exists($googleUser, 'getRaw')) {
            $raw = $googleUser->getRaw();
        } elseif (isset($googleUser->user) && is_array($googleUser->user)) {
            $raw = $googleUser->user;
        }

        $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        if (is_bool($verified)) {
            return $verified;
        }

        return filter_var($verified, FILTER_VALIDATE_BOOLEAN);
    }
}
