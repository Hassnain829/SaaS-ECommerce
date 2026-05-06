<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityLogRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?Request $request,
        string $eventType,
        string $severity = SecurityLog::SEVERITY_INFO,
        ?Store $store = null,
        ?User $user = null,
        ?User $targetUser = null,
        array $metadata = [],
    ): ?SecurityLog {
        $store ??= $request?->attributes->get('currentStore');
        $user ??= $request?->user();

        try {
            return SecurityLog::query()->create([
                'store_id' => $store?->id,
                'user_id' => $user?->id,
                'target_user_id' => $targetUser?->id,
                'event_type' => $eventType,
                'severity' => $severity,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => $metadata === [] ? null : $metadata,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Security log write failed', [
                'event_type' => $eventType,
                'store_id' => $store?->id,
                'user_id' => $user?->id,
                'target_user_id' => $targetUser?->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
