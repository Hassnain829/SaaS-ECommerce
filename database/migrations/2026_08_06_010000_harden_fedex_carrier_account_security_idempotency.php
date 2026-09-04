<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FEDEX_CARRIER_CODE = 'fedex';

    private const CONNECTION_MODEL_INTEGRATOR_PROVIDER = 'integrator_provider';

    private const CONNECTION_CONNECTED = 'connected';

    private const STATUS_ENABLED = 'enabled';

    public function up(): void
    {
        if (! Schema::hasTable('carrier_accounts')) {
            return;
        }

        Schema::table('carrier_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('carrier_accounts', 'provider_account_number_encrypted')) {
                $table->text('provider_account_number_encrypted')->nullable()->after('provider_account_number');
            }
            if (! Schema::hasColumn('carrier_accounts', 'provider_account_last4')) {
                $table->string('provider_account_last4', 4)->nullable()->after('provider_account_number_encrypted');
            }
            if (! Schema::hasColumn('carrier_accounts', 'fedex_active_store_key')) {
                $table->string('fedex_active_store_key', 64)->nullable()->after('registration_session_id');
            }
        });

        // Fail before mutating any row so conflicting data can be reviewed with the original state intact.
        $this->assertSingleConnectedModelAAccountPerStoreEnvironment();
        $this->assertRegistrationSessionOwnershipIntegrity();

        $this->backfillModelAAccountNumbers();
        $this->reconcileRegistrationSessionLinks();
        $this->backfillFedExActiveStoreKeys();

        if (
            Schema::hasColumn('carrier_accounts', 'registration_session_id')
            && ! $this->hasIndexNamed('carrier_accounts', 'carrier_accounts_registration_session_id_unique')
        ) {
            Schema::table('carrier_accounts', function (Blueprint $table): void {
                $table->unique('registration_session_id', 'carrier_accounts_registration_session_id_unique');
            });
        }

        if (
            Schema::hasColumn('carrier_accounts', 'fedex_active_store_key')
            && ! $this->hasIndexNamed('carrier_accounts', 'carrier_accounts_fedex_active_store_key_unique')
        ) {
            Schema::table('carrier_accounts', function (Blueprint $table): void {
                $table->unique('fedex_active_store_key', 'carrier_accounts_fedex_active_store_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('carrier_accounts')) {
            return;
        }

        // Plaintext must be recoverable before the encrypted column disappears.
        $this->restorePlaintextAccountNumbers();

        if ($this->hasIndexNamed('carrier_accounts', 'carrier_accounts_registration_session_id_unique')) {
            Schema::table('carrier_accounts', function (Blueprint $table): void {
                $table->dropUnique('carrier_accounts_registration_session_id_unique');
            });
        }

        if ($this->hasIndexNamed('carrier_accounts', 'carrier_accounts_fedex_active_store_key_unique')) {
            Schema::table('carrier_accounts', function (Blueprint $table): void {
                $table->dropUnique('carrier_accounts_fedex_active_store_key_unique');
            });
        }

        Schema::table('carrier_accounts', function (Blueprint $table): void {
            foreach (['fedex_active_store_key', 'provider_account_last4', 'provider_account_number_encrypted'] as $column) {
                if (Schema::hasColumn('carrier_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndexNamed(string $table, string $indexName): bool
    {
        if (method_exists(Schema::class, 'hasIndex')) {
            return Schema::hasIndex($table, $indexName);
        }

        try {
            $indexes = Schema::getConnection()->getSchemaBuilder()->getIndexes($table);

            return collect($indexes)->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * FedEx Model A integrator-provider accounts only.
     */
    private function fedExModelAQuery(bool $excludeSoftDeleted = false): Builder
    {
        $query = DB::table('carrier_accounts')
            ->where(function (Builder $carrierScope): void {
                $carrierScope->where('provider', self::FEDEX_CARRIER_CODE);

                if (Schema::hasTable('carriers')) {
                    $carrierScope->orWhereIn('carrier_id', function (Builder $carriers): void {
                        $carriers->select('id')
                            ->from('carriers')
                            ->where('code', self::FEDEX_CARRIER_CODE);
                    });
                }
            })
            ->where(function (Builder $modelScope): void {
                $modelScope->where('fedex_integrator_account', true)
                    ->orWhere('connection_model', self::CONNECTION_MODEL_INTEGRATOR_PROVIDER);
            });

        if ($excludeSoftDeleted && Schema::hasColumn('carrier_accounts', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function assertSingleConnectedModelAAccountPerStoreEnvironment(): void
    {
        $conflicts = $this->fedExModelAQuery(true)
            ->where('connection_status', self::CONNECTION_CONNECTED)
            ->orderBy('id')
            ->get(['id', 'store_id', 'environment'])
            ->groupBy(fn (object $account): string => $account->store_id.'|'.strtolower((string) $account->environment))
            ->filter(fn (Collection $accounts): bool => $accounts->count() > 1);

        if ($conflicts->isEmpty()) {
            return;
        }

        $details = $conflicts
            ->map(function (Collection $accounts, string $groupKey): string {
                [$storeId, $environment] = explode('|', $groupKey, 2);

                return sprintf(
                    'store %s / %s environment holds carrier_accounts ids [%s]',
                    $storeId,
                    $environment,
                    $accounts->pluck('id')->implode(', '),
                );
            })
            ->values()
            ->implode('; ');

        throw new RuntimeException(
            'Migration stopped: fedex_active_store_key cannot be assigned because more than one connected FedEx Model A '
            .'account exists for the same store and environment. Review these records and disconnect (or soft delete) the '
            .'accounts that must not remain active, then re-run the migration. Conflicts: '.$details.'.'
        );
    }

    private function backfillFedExActiveStoreKeys(): void
    {
        if (! Schema::hasColumn('carrier_accounts', 'fedex_active_store_key')) {
            return;
        }

        $this->fedExModelAQuery(true)
            ->where('connection_status', self::CONNECTION_CONNECTED)
            ->whereNull('fedex_active_store_key')
            ->orderBy('id')
            ->get(['id', 'store_id', 'environment'])
            ->each(function (object $account): void {
                DB::table('carrier_accounts')
                    ->where('id', $account->id)
                    ->update([
                        'fedex_active_store_key' => sprintf(
                            'store:%d:fedex:%s',
                            (int) $account->store_id,
                            strtolower((string) $account->environment),
                        ),
                    ]);
            });
    }

    private function backfillModelAAccountNumbers(): void
    {
        if (! Schema::hasColumn('carrier_accounts', 'provider_account_number_encrypted')) {
            return;
        }

        $this->fedExModelAQuery()
            ->whereNotNull('provider_account_number')
            ->where('provider_account_number', '!=', '')
            ->orderBy('id')
            ->get(['id', 'provider_account_number', 'provider_account_number_encrypted'])
            ->each(function (object $account): void {
                $digits = preg_replace('/\D+/', '', (string) $account->provider_account_number) ?? '';

                if ($digits === '') {
                    return;
                }

                $encrypted = filled($account->provider_account_number_encrypted)
                    ? $account->provider_account_number_encrypted
                    : Crypt::encryptString($digits);

                DB::table('carrier_accounts')
                    ->where('id', $account->id)
                    ->update([
                        'provider_account_number_encrypted' => $encrypted,
                        'provider_account_last4' => strlen($digits) >= 4 ? substr($digits, -4) : null,
                        'provider_account_number' => null,
                    ]);
            });
    }

    /**
     * Validate tenancy before any row is rewritten: a duplicated session may only be reconciled
     * against accounts that belong to the same store and provider as the session itself.
     */
    private function assertRegistrationSessionOwnershipIntegrity(): void
    {
        foreach ($this->duplicatedRegistrationSessionGroups() as [$sessionId, $candidates, $session]) {
            if ($session === null) {
                continue;
            }

            $consistent = $this->candidatesMatchingSessionOwnership($candidates, $session);

            if ($consistent->isNotEmpty()) {
                continue;
            }

            throw new RuntimeException($this->ownershipMismatchMessage($sessionId, $candidates, $session));
        }
    }

    private function reconcileRegistrationSessionLinks(): void
    {
        $hasSessionsTable = Schema::hasTable('carrier_account_registration_sessions');

        foreach ($this->duplicatedRegistrationSessionGroups() as [$sessionId, $candidates, $session]) {
            $keeper = $this->preferredAccountForSession($sessionId, $candidates, $session);

            if ($keeper === null) {
                continue;
            }

            if (
                $hasSessionsTable
                && $session !== null
                && (int) ($session->carrier_account_id ?? 0) !== (int) $keeper->id
            ) {
                DB::table('carrier_account_registration_sessions')
                    ->where('id', $sessionId)
                    ->update(['carrier_account_id' => $keeper->id]);
            }

            DB::table('carrier_accounts')
                ->where('registration_session_id', $sessionId)
                ->where('id', '!=', $keeper->id)
                ->update(['registration_session_id' => null]);
        }
    }

    /**
     * @return list<array{0: int, 1: Collection<int, object>, 2: object|null}>
     */
    private function duplicatedRegistrationSessionGroups(): array
    {
        if (! Schema::hasColumn('carrier_accounts', 'registration_session_id')) {
            return [];
        }

        $duplicatedSessionIds = DB::table('carrier_accounts')
            ->select('registration_session_id')
            ->whereNotNull('registration_session_id')
            ->groupBy('registration_session_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('registration_session_id');

        $hasSessionsTable = Schema::hasTable('carrier_account_registration_sessions');
        $groups = [];

        foreach ($duplicatedSessionIds as $sessionId) {
            $groups[] = [
                (int) $sessionId,
                DB::table('carrier_accounts')
                    ->where('registration_session_id', $sessionId)
                    ->orderBy('id')
                    ->get(),
                $hasSessionsTable
                    ? DB::table('carrier_account_registration_sessions')->where('id', $sessionId)->first()
                    : null,
            ];
        }

        return $groups;
    }

    /**
     * @param  Collection<int, object>  $candidates
     * @return Collection<int, object>
     */
    private function candidatesMatchingSessionOwnership(Collection $candidates, ?object $session): Collection
    {
        return $candidates
            ->filter(fn (object $account): bool => $this->matchesSessionOwnership($account, $session))
            ->values();
    }

    /**
     * @param  Collection<int, object>  $candidates
     */
    private function ownershipMismatchMessage(int $sessionId, Collection $candidates, object $session): string
    {
        $candidateDetails = $candidates
            ->map(fn (object $account): string => sprintf(
                'id %d (store %s, provider %s)',
                (int) $account->id,
                $account->store_id ?? 'null',
                $account->provider ?? 'null',
            ))
            ->implode(', ');

        return sprintf(
            'Migration stopped: registration session %d is linked to carrier accounts from a different store or provider, '
            .'so the duplicate cannot be reconciled safely. Expected store %s and provider %s. Candidates: %s. '
            .'Repoint or remove the cross-tenant carrier_accounts.registration_session_id values, then re-run the migration.',
            $sessionId,
            $session->store_id ?? 'null',
            $session->provider ?? 'null',
            $candidateDetails,
        );
    }

    /**
     * @param  Collection<int, object>  $candidates
     */
    private function preferredAccountForSession(int $sessionId, Collection $candidates, ?object $session): ?object
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($session === null) {
            // No session row to protect: deterministic fallback across all duplicates is acceptable.
            $pool = $candidates->values();
        } else {
            $pool = $this->candidatesMatchingSessionOwnership($candidates, $session);

            if ($pool->isEmpty()) {
                throw new RuntimeException($this->ownershipMismatchMessage($sessionId, $candidates, $session));
            }
        }

        $referencedId = (int) ($session->carrier_account_id ?? 0);
        if ($referencedId > 0) {
            $referenced = $pool->first(fn (object $account): bool => (int) $account->id === $referencedId);

            if ($referenced !== null) {
                return $referenced;
            }
        }

        $connected = $pool
            ->filter(fn (object $account): bool => ($account->connection_status ?? null) === self::CONNECTION_CONNECTED)
            ->sortBy('id')
            ->first();

        if ($connected !== null) {
            return $connected;
        }

        $enabledOrVerified = $pool
            ->filter(fn (object $account): bool => ($account->status ?? null) === self::STATUS_ENABLED
                || filled($account->last_verified_at ?? null))
            ->sortBy('id')
            ->first();

        if ($enabledOrVerified !== null) {
            return $enabledOrVerified;
        }

        return $pool->sortBy('id')->first();
    }

    private function matchesSessionOwnership(object $account, ?object $session): bool
    {
        if ($session === null) {
            return true;
        }

        if (isset($session->store_id) && (int) $account->store_id !== (int) $session->store_id) {
            return false;
        }

        $sessionProvider = strtolower((string) ($session->provider ?? ''));
        $accountProvider = strtolower((string) ($account->provider ?? ''));

        if ($sessionProvider !== '' && $accountProvider !== '' && $sessionProvider !== $accountProvider) {
            return false;
        }

        return true;
    }

    private function restorePlaintextAccountNumbers(): void
    {
        if (
            ! Schema::hasColumn('carrier_accounts', 'provider_account_number_encrypted')
            || ! Schema::hasColumn('carrier_accounts', 'provider_account_number')
        ) {
            return;
        }

        $encryptedAccounts = DB::table('carrier_accounts')
            ->whereNotNull('provider_account_number_encrypted')
            ->where('provider_account_number_encrypted', '!=', '')
            ->orderBy('id')
            ->get(['id', 'provider_account_number', 'provider_account_number_encrypted']);

        foreach ($encryptedAccounts as $account) {
            try {
                $plaintext = Crypt::decryptString((string) $account->provider_account_number_encrypted);
            } catch (Throwable $exception) {
                throw new RuntimeException(sprintf(
                    'Rollback aborted before dropping provider_account_number_encrypted: carrier account id %d could not be '
                    .'decrypted (%s). Restore the APP_KEY used to encrypt these FedEx account numbers, then roll back again.',
                    (int) $account->id,
                    $exception->getMessage(),
                ), 0, $exception);
            }

            DB::table('carrier_accounts')
                ->where('id', $account->id)
                ->update(['provider_account_number' => $plaintext]);

            $restored = (string) DB::table('carrier_accounts')
                ->where('id', $account->id)
                ->value('provider_account_number');

            if ($restored !== $plaintext) {
                throw new RuntimeException(sprintf(
                    'Rollback aborted before dropping provider_account_number_encrypted: carrier account id %d did not restore '
                    .'its plaintext FedEx account number. Inspect the record manually before retrying the rollback.',
                    (int) $account->id,
                ));
            }
        }
    }
};
