<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OnboardingRouteRegressionTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const BASE_MIDDLEWARE = [
        'auth',
        'role:user',
        'current.store',
    ];

    /** @var list<array{name: string, uri: string, methods: list<string>, middleware: list<string>, controller: class-string, action: string}> */
    private const ONBOARDING_ROUTE_CONTRACTS = [
        [
            'name' => 'onboarding-StoreDetails-1',
            'uri' => 'onboarding-StoreDetails-1',
            'methods' => ['GET'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'step1',
        ],
        [
            'name' => 'onboarding-StoreDetails-1.store',
            'uri' => 'onboarding-StoreDetails-1',
            'methods' => ['POST'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'storeStep1',
        ],
        [
            'name' => 'AddCustomCategoryOverlay',
            'uri' => 'AddCustomCategoryOverlay',
            'methods' => ['GET'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'customCategoryOverlay',
        ],
        [
            'name' => 'AddCustomCategoryOverlay.store',
            'uri' => 'AddCustomCategoryOverlay',
            'methods' => ['POST'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'storeCustomCategoryOverlay',
        ],
        [
            'name' => 'onboarding-Step2-AddProductVariations',
            'uri' => 'onboarding-Step2-AddProductVariations',
            'methods' => ['GET'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'step2',
        ],
        [
            'name' => 'onboarding-Step2-AddProductVariations.store',
            'uri' => 'onboarding-Step2-AddProductVariations',
            'methods' => ['POST'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'storeStep2',
        ],
        [
            'name' => 'onboarding_AddProduct_VariationsPopup',
            'uri' => 'onboarding-Step2-VariationsPopup',
            'methods' => ['GET'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'variationPopup',
        ],
        [
            'name' => 'onboarding_AddProduct_VariationsPopup.store',
            'uri' => 'onboarding-Step2-VariationsPopup',
            'methods' => ['POST'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'storeVariationPopup',
        ],
        [
            'name' => 'onboarding_StoreReady',
            'uri' => 'onboarding-Step3-StoreReady',
            'methods' => ['GET'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'step3',
        ],
        [
            'name' => 'onboarding_StoreReady.complete',
            'uri' => 'onboarding-Step3-StoreReady',
            'methods' => ['POST'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'completeStep3',
        ],
        [
            'name' => 'store.update',
            'uri' => 'store/{storeId}',
            'methods' => ['PUT'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'updateStoreFromManagement',
        ],
        [
            'name' => 'store.destroy',
            'uri' => 'store/{storeId}',
            'methods' => ['DELETE'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'destroyStoreFromManagement',
        ],
        [
            'name' => 'product.store',
            'uri' => 'products',
            'methods' => ['POST'],
            'middleware' => [...self::BASE_MIDDLEWARE, 'store.permission:catalog.manage'],
            'controller' => OnboardingController::class,
            'action' => 'storeProductFromCurrentStore',
        ],
        [
            'name' => 'product.update',
            'uri' => 'product/{productId}',
            'methods' => ['PUT'],
            'middleware' => [...self::BASE_MIDDLEWARE, 'store.permission:catalog.manage'],
            'controller' => OnboardingController::class,
            'action' => 'updateProductFromManagement',
        ],
        [
            'name' => 'product.destroy',
            'uri' => 'product/{productId}',
            'methods' => ['DELETE'],
            'middleware' => [...self::BASE_MIDDLEWARE, 'store.permission:catalog.manage'],
            'controller' => OnboardingController::class,
            'action' => 'destroyProductFromManagement',
        ],
        [
            'name' => 'store.add-product',
            'uri' => 'store/{storeId}/add-product',
            'methods' => ['GET'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'addProductFromStore',
        ],
        [
            'name' => 'store.add-product.store',
            'uri' => 'store/{storeId}/add-product',
            'methods' => ['POST'],
            'middleware' => self::BASE_MIDDLEWARE,
            'controller' => OnboardingController::class,
            'action' => 'storeProductFromStore',
        ],
    ];

    public function test_extracted_onboarding_routes_match_clean_4_contracts(): void
    {
        foreach (self::ONBOARDING_ROUTE_CONTRACTS as $contract) {
            $this->assertRouteContract($contract);
        }
    }

    /**
     * @param  array{name: string, uri: string, methods: list<string>, middleware: list<string>, controller: class-string, action: string}  $contract
     */
    private function assertRouteContract(array $contract): void
    {
        $route = Route::getRoutes()->getByName($contract['name']);
        $this->assertNotNull($route, "Missing route: {$contract['name']}");

        $this->assertSame($contract['uri'], $route->uri(), "URI mismatch for {$contract['name']}");

        foreach ($contract['methods'] as $method) {
            $this->assertContains($method, $route->methods(), "HTTP method {$method} missing for {$contract['name']}");
        }

        $middleware = $route->gatherMiddleware();
        foreach ($contract['middleware'] as $expected) {
            $this->assertContains(
                $expected,
                $middleware,
                "Middleware {$expected} missing for {$contract['name']}. Actual: ".implode(', ', $middleware),
            );
        }

        $action = (string) $route->getAction('controller');
        [$controller, $method] = array_pad(explode('@', $action), 2, null);

        $this->assertSame($contract['controller'], $controller, "Controller mismatch for {$contract['name']}");
        $this->assertSame($contract['action'], $method, "Action mismatch for {$contract['name']}");
    }
}
