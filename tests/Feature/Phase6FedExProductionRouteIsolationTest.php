<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Phase6FedExProductionRouteIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
    }

    public function test_validation_routes_are_fully_retired(): void
    {
        $this->assertFileDoesNotExist(base_path('routes/fedex-validation.php'));

        $this->assertFalse(Route::has('settings.shipping.carrier-accounts.fedex.validation'));
    }

    public function test_model_b_routes_gate_uses_fedex_config_method(): void
    {
        $source = file_get_contents(base_path('routes/fedex.php'));
        $this->assertStringContainsString('modelBRoutesEnabled()', $source);

        config([
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.developer_mode_enabled' => false,
        ]);
        $this->assertFalse(app(FedExConfig::class)->modelBRoutesEnabled());
    }

    public function test_model_b_settings_actions_404_when_gate_disabled(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.developer_mode_enabled' => false,
        ]);

        [$owner, $store] = $this->ownerStore('FedEx Model B Isolated');

        if (! Route::has('settings.shipping.carrier-accounts.fedex.store')) {
            $this->assertTrue(true);

            return;
        }

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), [
                'display_name' => 'Model B',
                'environment' => 'sandbox',
                'provider_account_number' => '700257037',
                'company_name' => 'Acme',
                'contact_name' => 'Jane',
                'address_line1' => '1 Main',
                'city' => 'Memphis',
                'state' => 'TN',
                'postal_code' => '38118',
                'country_code' => 'US',
                'phone' => '+19015550100',
                'email' => 'a@example.test',
            ])
            ->assertNotFound();
    }

    public function test_named_fedex_rate_limiters_are_registered(): void
    {
        $submit = collect(Route::getRoutes())->first(
            fn ($route) => $route->getName() === 'settings.shipping.fedex-integrator.account.submit'
        );
        $verify = collect(Route::getRoutes())->first(
            fn ($route) => $route->getName() === 'settings.shipping.fedex-integrator.verify'
        );
        $resume = collect(Route::getRoutes())->first(
            fn ($route) => $route->getName() === 'settings.shipping.fedex-integrator.resume'
        );

        $this->assertNotNull($submit);
        $this->assertNotNull($resume);
        $this->assertContains('throttle:fedex-registration', $submit->gatherMiddleware());
        $this->assertContains('throttle:fedex-connection-check', $verify->gatherMiddleware());
        $this->assertContains('throttle:fedex-connection-check', $resume->gatherMiddleware());
    }

    public function test_route_files_have_no_utf8_bom(): void
    {
        foreach (['routes/carriers.php', 'routes/fedex.php', 'routes/usps.php'] as $relative) {
            $bytes = file_get_contents(base_path($relative), false, null, 0, 3);
            $this->assertNotSame("\xEF\xBB\xBF", $bytes, $relative.' still has BOM');
        }
    }

    public function test_production_child_process_hides_validation_and_model_b_routes(): void
    {
        $names = $this->routeNamesFromArtisanChildProcess([
            'APP_ENV' => 'production',
            'FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED' => 'true',
            'FEDEX_DEVELOPER_MODE_ENABLED' => 'true',
        ]);

        $this->assertModelALifecycleRoutesPresent($names);
        $this->assertFalse(
            $names->contains(fn (string $name) => str_contains($name, 'fedex.validation')),
            'Validation routes must not boot in production'
        );
        $this->assertFalse($names->contains('settings.shipping.carrier-accounts.fedex.store'));
        $this->assertFalse($names->contains('settings.shipping.carrier-accounts.fedex.test'));
        $this->assertFalse($names->contains('settings.shipping.carrier-accounts.fedex.registration.update'));
    }

    public function test_testing_child_process_validation_routes_never_boot(): void
    {
        $absent = $this->routeNamesFromArtisanChildProcess([
            'APP_ENV' => 'testing',
            'FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED' => 'false',
            'FEDEX_DEVELOPER_MODE_ENABLED' => 'false',
        ]);
        $this->assertModelALifecycleRoutesPresent($absent);
        $this->assertFalse($absent->contains(fn (string $name) => str_contains($name, 'fedex.validation')));
    }

    /**
     * @param  array<string, string>  $env
     * @return Collection<int, string>
     */
    private function routeNamesFromArtisanChildProcess(array $env): Collection
    {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'route:list', '--json'],
            base_path(),
            array_merge($_ENV, $_SERVER, $env),
        );
        $process->setTimeout(120);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "route:list failed ({$process->getExitCode()}): ".$process->getErrorOutput().$process->getOutput()
        );

        $decoded = json_decode($process->getOutput(), true);
        $this->assertIsArray($decoded);

        return collect($decoded)
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values();
    }

    /**
     * @param  Collection<int, string>  $names
     */
    private function assertModelALifecycleRoutesPresent(Collection $names): void
    {
        foreach ([
            'settings.shipping.fedex-integrator.manage',
            'settings.shipping.fedex-integrator.verify',
            'settings.shipping.fedex-integrator.resume',
            'settings.shipping.fedex-integrator.reconnect',
            'settings.shipping.fedex-integrator.disconnect',
        ] as $routeName) {
            $this->assertTrue($names->contains($routeName), "Missing Model A route: {$routeName}");
        }
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => $role->id,
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
}
