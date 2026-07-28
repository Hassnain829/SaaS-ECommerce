<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ensures notification side-effects never join commerce transactions.
 *
 * When called inside a DB transaction, work runs after commit.
 * On rollback, afterCommit callbacks are discarded — no notifications leak.
 * Exceptions are logged and swallowed so commerce paths stay intact.
 */
final class NotificationCommitBoundary
{
    /**
     * @param  callable(): void  $callback
     */
    public function run(string $context, callable $callback, array $meta = []): void
    {
        $runner = function () use ($context, $callback, $meta): void {
            try {
                $callback();
            } catch (Throwable $e) {
                Log::error('notification_dispatch_failed', array_merge([
                    'context' => $context,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ], $meta));
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($runner);

            return;
        }

        $runner();
    }
}
