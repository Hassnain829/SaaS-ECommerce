<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserSessionTracker
{
    public function touch(Request $request): ?UserSession
    {
        $user = $request->user();
        $sessionId = $this->sessionId($request);

        if (! $user || $sessionId === '') {
            return null;
        }

        $description = $this->describeUserAgent((string) $request->userAgent());

        UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', '!=', $sessionId)
            ->whereNull('revoked_at')
            ->update(['is_current' => false]);

        return UserSession::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'session_id' => $sessionId,
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'browser' => $description['browser'],
                'os' => $description['os'],
                'device_type' => $description['device_type'],
                'location' => null,
                'last_activity' => now(),
                'ended_at' => null,
                'is_current' => true,
            ]
        );
    }

    public function currentSessionIsRevoked(Request $request): bool
    {
        $user = $request->user();
        $sessionId = $this->sessionId($request);

        if (! $user || $sessionId === '') {
            return false;
        }

        return UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->whereNotNull('revoked_at')
            ->exists();
    }

    public function revoke(UserSession $session): void
    {
        $endedAt = now();

        $session->update([
            'revoked_at' => $endedAt,
            'ended_at' => $endedAt,
            'is_current' => false,
        ]);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('id', $session->session_id)->delete();
        }
    }

    public function sessionId(Request $request): string
    {
        return $request->hasSession() ? (string) $request->session()->getId() : '';
    }

    /**
     * @return array{browser: string, os: string, device_type: string}
     */
    private function describeUserAgent(string $userAgent): array
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Chrome/') && ! str_contains($userAgent, 'Edg/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome/') => 'Safari',
            str_contains($userAgent, 'PostmanRuntime') => 'Postman',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        $deviceType = match (true) {
            str_contains($userAgent, 'iPad') || str_contains($userAgent, 'Tablet') => 'Tablet',
            str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'Android') => 'Mobile',
            default => 'Desktop',
        };

        return [
            'browser' => $browser,
            'os' => $os,
            'device_type' => $deviceType,
        ];
    }
}
