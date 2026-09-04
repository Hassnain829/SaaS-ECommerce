<?php

namespace Tests\Feature;

use App\Http\Controllers\Carrier\Connection\CarrierConnectionWizardController;
use App\Http\Controllers\Carrier\Connection\FedExIntegratorConnectionController;
use App\Http\Controllers\Carrier\Connection\USPSMerchantConnectionController;
use App\Http\Controllers\Settings\FedExShippingSettingsController;
use App\Http\Controllers\Settings\ShippingSettingsController;
use App\Http\Controllers\Settings\USPSShippingSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CarrierRouteOwnershipSeparationTest extends TestCase
{
    /**
     * @return list<array{name: string, methods: list<string>, uri: string, action: string, middleware: list<string>}>
     */
    private function expectedMovedRoutes(): array
    {
        return [
            [
                'name' => 'settings.shipping.fedex-integrator.start',
                'methods' => ['GET', 'HEAD'],
                'uri' => 'settings/shipping/carriers/connect/fedex-integrator',
                'action' => FedExIntegratorConnectionController::class.'@start',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.fedex-integrator.origin',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carriers/connect/fedex-integrator/origin',
                'action' => FedExIntegratorConnectionController::class.'@storeOrigin',
                'middleware' => ['store.permission:settings.manage', 'throttle:fedex-registration'],
            ],
            [
                'name' => 'settings.shipping.fedex-integrator.resume',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carriers/connect/fedex-integrator/{session}/resume',
                'action' => FedExIntegratorConnectionController::class.'@resume',
                'middleware' => ['store.permission:settings.manage', 'throttle:fedex-connection-check'],
            ],
            [
                'name' => 'settings.shipping.fedex-integrator.manage',
                'methods' => ['GET', 'HEAD'],
                'uri' => 'settings/shipping/carrier-accounts/{carrierAccount}/fedex/manage',
                'action' => FedExIntegratorConnectionController::class.'@manage',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.fedex-integrator.verify',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carrier-accounts/{carrierAccount}/fedex/verify',
                'action' => FedExIntegratorConnectionController::class.'@verify',
                'middleware' => ['store.permission:settings.manage', 'throttle:fedex-connection-check'],
            ],
            [
                'name' => 'settings.shipping.fedex-integrator.reconnect',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carrier-accounts/{carrierAccount}/fedex/reconnect',
                'action' => FedExIntegratorConnectionController::class.'@reconnect',
                'middleware' => ['store.permission:settings.manage', 'throttle:fedex-registration'],
            ],
            [
                'name' => 'settings.shipping.fedex-integrator.disconnect',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carrier-accounts/{carrierAccount}/fedex/disconnect',
                'action' => FedExIntegratorConnectionController::class.'@disconnect',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.usps-merchant.start',
                'methods' => ['GET', 'HEAD'],
                'uri' => 'settings/shipping/carriers/connect/usps-merchant',
                'action' => USPSMerchantConnectionController::class.'@start',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.usps-merchant.origin',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carriers/connect/usps-merchant/origin',
                'action' => USPSMerchantConnectionController::class.'@storeOrigin',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.usps-merchant.manage',
                'methods' => ['GET', 'HEAD'],
                'uri' => 'settings/shipping/carrier-accounts/{carrierAccount}/usps/manage',
                'action' => USPSMerchantConnectionController::class.'@manage',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.usps-merchant.disconnect',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carrier-accounts/{carrierAccount}/usps/disconnect',
                'action' => USPSMerchantConnectionController::class.'@disconnect',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.carrier-accounts.usps.store',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carrier-accounts/usps',
                'action' => USPSShippingSettingsController::class.'@storeUspsCarrierAccount',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.carrier-accounts.usps.test',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/carrier-accounts/{carrierAccount}/usps/test',
                'action' => USPSShippingSettingsController::class.'@testUspsCarrierAccount',
                'middleware' => ['store.permission:settings.manage'],
            ],
            [
                'name' => 'settings.shipping.usps.test-package-quote',
                'methods' => ['POST'],
                'uri' => 'settings/shipping/usps/test-package-quote',
                'action' => USPSShippingSettingsController::class.'@storeUspsTestPackage',
                'middleware' => ['store.permission:settings.manage'],
            ],
        ];
    }

    public function test_moved_routes_preserve_full_contract(): void
    {
        foreach ($this->expectedMovedRoutes() as $expected) {
            $route = Route::getRoutes()->getByName($expected['name']);
            $this->assertNotNull($route, 'Missing route: '.$expected['name']);

            $this->assertSame($expected['uri'], $route->uri());
            $this->assertSame($expected['action'], $route->getActionName());
            $this->assertEqualsCanonicalizing($expected['methods'], $route->methods());

            foreach ($expected['middleware'] as $middleware) {
                $this->assertContains(
                    $middleware,
                    $route->gatherMiddleware(),
                    "Route {$expected['name']} missing middleware {$middleware}"
                );
            }
        }
    }

    public function test_shared_carrier_routes_remain_on_shipping_settings_controller(): void
    {
        foreach ([
            'settings.shipping.carrier-accounts.disable' => 'disableCarrierAccount',
            'settings.shipping.carrier-accounts.store' => 'storeCarrierAccount',
            'settings.shipping.carrier-accounts.update' => 'updateCarrierAccount',
            'settings.shipping.carrier-accounts.destroy' => 'destroyCarrierAccount',
            'shipping.carriers.connect.index' => null,
            'shipping.carriers.connect.show' => null,
        ] as $name => $method) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, 'Missing shared route: '.$name);

            if ($method !== null) {
                $this->assertSame(ShippingSettingsController::class.'@'.$method, $route->getActionName());
            }
        }

        $this->assertSame(
            CarrierConnectionWizardController::class.'@index',
            Route::getRoutes()->getByName('shipping.carriers.connect.index')->getActionName()
        );
        $this->assertSame(
            CarrierConnectionWizardController::class.'@show',
            Route::getRoutes()->getByName('shipping.carriers.connect.show')->getActionName()
        );
    }

    public function test_no_duplicate_route_names_for_carrier_routes(): void
    {
        $names = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->filter(fn (string $name): bool => str_contains($name, 'fedex')
                || str_contains($name, 'usps')
                || str_contains($name, 'carriers.connect')
                || str_contains($name, 'carrier-accounts'));

        $this->assertSame(
            $names->count(),
            $names->unique()->count(),
            'Duplicate carrier-related route names detected: '.json_encode(
                $names->duplicates()->values()->all()
            )
        );
    }

    public function test_specific_connect_paths_are_not_shadowed_by_carrier_wildcard(): void
    {
        $integrator = Route::getRoutes()->getByName('settings.shipping.fedex-integrator.start');
        $usps = Route::getRoutes()->getByName('settings.shipping.usps-merchant.start');
        $wildcard = Route::getRoutes()->getByName('shipping.carriers.connect.show');

        $this->assertNotNull($integrator);
        $this->assertNotNull($usps);
        $this->assertNotNull($wildcard);

        $this->assertLessThan(
            $wildcard->getAction('uses') ? array_search($wildcard, Route::getRoutes()->getRoutes(), true) : PHP_INT_MAX,
            array_search($integrator, Route::getRoutes()->getRoutes(), true)
        );

        $matchedFedEx = Route::getRoutes()->match(
            Request::create('/settings/shipping/carriers/connect/fedex-integrator', 'GET')
        );
        $this->assertSame('settings.shipping.fedex-integrator.start', $matchedFedEx->getName());

        $matchedUsps = Route::getRoutes()->match(
            Request::create('/settings/shipping/carriers/connect/usps-merchant', 'GET')
        );
        $this->assertSame('settings.shipping.usps-merchant.start', $matchedUsps->getName());
    }

    public function test_route_files_do_not_cross_import_carriers(): void
    {
        $fedex = file_get_contents(base_path('routes/fedex.php'));
        $usps = file_get_contents(base_path('routes/usps.php'));

        $this->assertIsString($fedex);
        $this->assertIsString($usps);

        $this->assertStringNotContainsString('USPSMerchantConnectionController', $fedex);
        $this->assertStringNotContainsString('USPSShippingSettingsController', $fedex);
        $this->assertDoesNotMatchRegularExpression('/use\s+[^;]*Usps/i', $fedex);

        $this->assertStringNotContainsString('FedExIntegratorConnectionController', $usps);
        $this->assertStringNotContainsString('FedExShippingSettingsController', $usps);
        $this->assertDoesNotMatchRegularExpression('/use\s+[^;]*FedEx/i', $usps);
    }

    public function test_shipping_settings_controller_no_longer_owns_carrier_actions(): void
    {
        $this->assertFalse(method_exists(ShippingSettingsController::class, 'storeFedExCarrierAccount'));
        $this->assertFalse(method_exists(ShippingSettingsController::class, 'testFedExCarrierAccount'));
        $this->assertFalse(method_exists(ShippingSettingsController::class, 'storeUspsCarrierAccount'));
        $this->assertFalse(method_exists(ShippingSettingsController::class, 'testUspsCarrierAccount'));
        $this->assertFalse(method_exists(ShippingSettingsController::class, 'storeUspsTestPackage'));

        $this->assertTrue(method_exists(FedExShippingSettingsController::class, 'storeFedExCarrierAccount'));
        $this->assertTrue(method_exists(USPSShippingSettingsController::class, 'storeUspsCarrierAccount'));
        $this->assertTrue(method_exists(ShippingSettingsController::class, 'index'));
    }

    public function test_route_list_boots_successfully(): void
    {
        $exit = Artisan::call('route:list');
        $this->assertSame(0, $exit);
        $this->assertTrue(Route::has('settings.shipping.fedex-integrator.start'));
        $this->assertTrue(Route::has('settings.shipping.usps-merchant.start'));
        $this->assertNotSame('', trim(Artisan::output()));
    }
}
