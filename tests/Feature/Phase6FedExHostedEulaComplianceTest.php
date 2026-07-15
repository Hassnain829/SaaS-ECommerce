<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\FedExValidationArtifact;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Connection\FedExEulaService;
use App\Services\Carriers\FedEx\Connection\FedExIntegratorRegistrationOrchestrator;
use App\Services\Carriers\FedEx\Validation\FedExHostedEulaEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class Phase6FedExHostedEulaComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExModelA();
        $this->configureOfficialEula();
    }

    public function test_official_eula_service_validates_pdf_hash_and_metadata(): void
    {
        $service = app(FedExEulaService::class);

        $this->assertTrue($service->isAvailable());
        $this->assertTrue($service->isValid());
        $this->assertSame(11, $service->expectedPages());
        $this->assertSame('2002382', $service->formNumber());
        $this->assertSame('3eea76a66fbae1d798c2069934ec9c2750c75e8f47879697cec16c84c47e8ab8', $service->hash());
    }

    public function test_wrong_configured_hash_makes_document_invalid(): void
    {
        config(['carriers.fedex.integrator_eula_sha256' => str_repeat('a', 64)]);

        $this->assertFalse(app(FedExEulaService::class)->isValid());
    }

    public function test_acceptance_requires_server_side_scroll_completion(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('EULA Scroll Required Store');
        $session = $this->createSession($store, $owner, $location);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.eula.accept', $session), [
                'read_and_accept_eula' => '1',
                'document_hash' => app(FedExEulaService::class)->hash(),
            ])
            ->assertSessionHasErrors('eula');

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED, $session->status);
    }

    public function test_eula_page_shows_eleven_page_status_and_scroll_lock_copy(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('EULA Status Copy Store');
        $session = $this->createSession($store, $owner, $location);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.eula', $session))
            ->assertOk()
            ->assertSeeText('11 pages')
            ->assertSee('of 11 pages loaded', false)
            ->assertSeeText('Acceptance locked until the end of page 11')
            ->assertSeeText('All 11 pages reviewed');
    }

    public function test_validation_eula_review_creates_session_and_copies_acceptance_to_account(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Validation EULA Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.eula-review', $account))
            ->assertRedirect();

        $session = CarrierAccountRegistrationSession::query()
            ->where('carrier_account_id', $account->id)
            ->where('purpose', CarrierAccountRegistrationSession::PURPOSE_VALIDATION_EULA)
            ->latest('id')
            ->first();

        $this->assertNotNull($session);
        $this->completeEulaScroll($session);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.eula.accept', $session), [
                'read_and_accept_eula' => '1',
                'document_hash' => app(FedExEulaService::class)->hash(),
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account));

        $account->refresh();
        $this->assertNotNull($account->eula_accepted_at);
        $this->assertSame(app(FedExEulaService::class)->hash(), $account->eula_document_hash);
        $this->assertSame(app(FedExEulaService::class)->version(), $account->eula_version);
    }

    public function test_legacy_placeholder_acceptance_fails_current_preflight(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Legacy EULA Store');
        $account->forceFill([
            'eula_accepted_at' => now()->subDay(),
            'eula_version' => '1.0',
            'eula_document_hash' => null,
        ])->save();

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);
        $acceptance = collect($assessment['checks'])->firstWhere('key', 'hosted_eula_acceptance');

        $this->assertSame('outdated', $acceptance['status']);
    }

    public function test_eula_evidence_upload_requires_current_acceptance(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('EULA Upload Blocked Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.eula-evidence.upload', $account), [
                'full_ui_evidence' => UploadedFile::fake()->create('full-ui.pdf', 100, 'application/pdf'),
                'acceptance_confirmation' => UploadedFile::fake()->create('confirm.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_diagnostic_export_includes_hosted_eula_folder(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('EULA Export Store');
        $this->acceptHostedEulaForAccount($owner, $store, $account);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.eula-evidence.upload', $account), [
                'full_ui_evidence' => UploadedFile::fake()->create('full-ui.pdf', 100, 'application/pdf'),
                'acceptance_confirmation' => UploadedFile::fake()->create('confirm.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $zipPath = app(FedExValidationEvidenceExporter::class)->exportDiagnostic($store, $account);
        $zip = new ZipArchive;
        $zip->open($zipPath);

        $this->assertNotFalse($zip->locateName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/01_registration_mfa/13_hosted_eula/official_eula.pdf'));
        $this->assertNotFalse($zip->locateName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/01_registration_mfa/13_hosted_eula/eula_document_metadata.json'));
        $this->assertNotFalse($zip->locateName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/01_registration_mfa/13_hosted_eula/eula_acceptance_record.json'));

        $zip->close();
    }

    public function test_eula_document_route_requires_store_session_authorization(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('EULA Document Route Store');
        $session = $this->createSession($store, $owner, $location);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.eula.document', $session))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function configureFedExModelA(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
        ]);
    }

    private function configureOfficialEula(): void
    {
        $path = base_path('resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf');
        $this->assertFileExists($path);

        config([
            'carriers.fedex.integrator_eula_path' => 'resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf',
            'carriers.fedex.integrator_eula_version' => 'FedEx Form No. 2002382 v 4 June 2024 Rev',
            'carriers.fedex.integrator_eula_form_number' => '2002382',
            'carriers.fedex.integrator_eula_expected_pages' => 11,
            'carriers.fedex.integrator_eula_sha256' => hash_file('sha256', $path),
        ]);
    }

    /**
     * @return array{0: User, 1: Store, 2: Location}
     */
    private function fixtureParts(string $storeName): array
    {
        [$owner, $store] = $this->ownerStore($storeName);
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '1751 THOMPSON ST',
            'city' => 'AURORA',
            'state' => 'OH',
            'postal_code' => '44202',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        return [$owner, $store, $location];
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        return [$owner, $store];
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function integratorAccountFixture(string $storeName): array
    {
        [$owner, $store, $location] = $this->fixtureParts($storeName);
        $fedex = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedex->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'FedEx Sandbox Validation',
            'provider_account_number' => '700257037',
            'default_origin_location_id' => $location->id,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'connection_context_json' => [
                'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            ],
            'created_by' => $owner->id,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $account->setCredentials([
            'customer_key' => 'existing-child-key',
            'customer_password' => 'existing-child-secret',
        ]);
        $account->save();

        return [$owner, $store, $account];
    }

    private function createSession(Store $store, User $owner, Location $location): CarrierAccountRegistrationSession
    {
        return CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'purpose' => CarrierAccountRegistrationSession::PURPOSE_CONNECTION,
            'status' => CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED,
            'origin_location_id' => $location->id,
            'eula_version' => app(FedExEulaService::class)->version(),
            'created_by' => $owner->id,
        ]);
    }

    private function completeEulaScroll(CarrierAccountRegistrationSession $session): void
    {
        app(FedExIntegratorRegistrationOrchestrator::class)->markEulaScrollComplete(
            $session,
            app(FedExEulaService::class)->hash(),
            app(FedExEulaService::class)->expectedPages(),
        );
    }

    private function acceptHostedEulaForAccount(User $owner, Store $store, CarrierAccount $account): CarrierAccountRegistrationSession
    {
        $session = app(FedExIntegratorRegistrationOrchestrator::class)->beginValidationEulaReview($store, $owner, $account);
        $this->completeEulaScroll($session);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.eula.accept', $session), [
                'read_and_accept_eula' => '1',
                'document_hash' => app(FedExEulaService::class)->hash(),
            ])
            ->assertRedirect();

        return $session->refresh();
    }
}
