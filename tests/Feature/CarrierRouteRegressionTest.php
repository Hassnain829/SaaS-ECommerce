<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CarrierRouteRegressionTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const CARRIER_ROUTE_NAMES = [
        'shipping.carriers.connect.index',
        'settings.shipping.fedex-integrator.start',
        'settings.shipping.carrier-accounts.fedex.validation',
        'settings.shipping.carrier-accounts.fedex.validation.run.address',
        'settings.shipping.carrier-accounts.fedex.test-address',
        'settings.shipping.carrier-accounts.usps.test',
        'settings.shipping.carrier-accounts.store',
    ];

    /** @var array<string, string> */
    private const CARRIER_CONTROLLER_ROUTES = [
        'shipping.carriers.connect.index' => 'App\\Http\\Controllers\\Carrier\\Connection\\CarrierConnectionWizardController',
        'settings.shipping.fedex-integrator.start' => 'App\\Http\\Controllers\\Carrier\\Connection\\FedExIntegratorConnectionController',
        'settings.shipping.carrier-accounts.fedex.validation' => 'App\\Http\\Controllers\\Carrier\\Validation\\FedExValidationWorkspaceController',
        'settings.shipping.carrier-accounts.fedex.validation.run.address' => 'App\\Http\\Controllers\\Carrier\\Validation\\FedExValidationRunController',
        'settings.shipping.carrier-accounts.fedex.test-address' => 'App\\Http\\Controllers\\Carrier\\Operations\\FedExCarrierTestController',
    ];

    public function test_key_carrier_routes_remain_registered_after_clean_2_extraction(): void
    {
        foreach (self::CARRIER_ROUTE_NAMES as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_carrier_routes_still_point_to_carrier_namespace_controllers(): void
    {
        foreach (self::CARRIER_CONTROLLER_ROUTES as $name => $expectedController) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $action = (string) $route->getAction('controller');
            $this->assertStringStartsWith($expectedController, explode('@', $action)[0]);
        }
    }
}
