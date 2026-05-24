<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrentStoreController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperStorefrontSettingsController;
use App\Http\Controllers\DraftOrderController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentSettingsController;
use App\Http\Controllers\ProductBulkController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductWorkspaceController;
use App\Http\Controllers\ProductWorkspaceDataController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShippingSettingsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

//
Route::get('/', function () {
    return view('user_view.welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/signin', [DashboardController::class, 'signin'])->name('signin');
    Route::post('/signin', [DashboardController::class, 'authenticate'])->name('signin.attempt');
    Route::get('/register', [DashboardController::class, 'register'])->name('register');
    Route::post('/register', [DashboardController::class, 'storeRegistration'])->name('register.store');
});

Route::get('/logout', [DashboardController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:user', 'current.store'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/products', [DashboardController::class, 'product'])->name('products');
    Route::get('/products/create', [DashboardController::class, 'createProduct'])
        ->middleware('store.permission:catalog.manage')
        ->name('products.create');
    Route::post('/products/catalog-list-highlights', [DashboardController::class, 'saveProductListDetailKeys'])
        ->middleware('store.permission:catalog.manage')
        ->name('products.catalog-list-highlights');
    Route::get('/products/primary-images', [DashboardController::class, 'productPrimaryImages'])->name('products.primary-images');
    Route::get('/products/view/{product}', [ProductWorkspaceController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/edit', [ProductWorkspaceController::class, 'edit'])
        ->middleware('store.permission:catalog.manage')
        ->name('products.edit');
    Route::post('/products/{product}/workspace/import-extra/promote', [ProductWorkspaceDataController::class, 'promoteImportExtra'])
        ->middleware('store.permission:catalog.manage')
        ->name('products.workspace.promote-import-extra');
    Route::post('/products/{product}/workspace/import-extra/apply-category', [ProductWorkspaceDataController::class, 'applyImportCategory'])
        ->middleware('store.permission:catalog.manage')
        ->name('products.workspace.apply-import-category');
    Route::post('/products/bulk', [ProductBulkController::class, 'handle'])
        ->middleware('store.permission:catalog.manage')
        ->name('products.bulk');

    Route::post('/brands', [BrandController::class, 'store'])
        ->middleware('store.permission:catalog.manage')
        ->name('brands.store');
    Route::patch('/brands/{brand}', [BrandController::class, 'update'])
        ->middleware('store.permission:catalog.manage')
        ->name('brands.update');
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])
        ->middleware('store.permission:catalog.manage')
        ->name('brands.destroy');
    Route::post('/tags', [TagController::class, 'store'])
        ->middleware('store.permission:catalog.manage')
        ->name('tags.store');
    Route::patch('/tags/{tag}', [TagController::class, 'update'])
        ->middleware('store.permission:catalog.manage')
        ->name('tags.update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])
        ->middleware('store.permission:catalog.manage')
        ->name('tags.destroy');
    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware('store.permission:catalog.manage')
        ->name('categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])
        ->middleware('store.permission:catalog.manage')
        ->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware('store.permission:catalog.manage')
        ->name('categories.destroy');
    Route::get('/catalog/attributes', [AttributeController::class, 'index'])
        ->middleware('store.permission:catalog.view')
        ->name('catalog.attributes.index');
    Route::post('/catalog/attributes', [AttributeController::class, 'store'])
        ->middleware('store.permission:catalog.manage')
        ->name('catalog.attributes.store');
    Route::patch('/catalog/attributes/{attribute}', [AttributeController::class, 'update'])
        ->middleware('store.permission:catalog.manage')
        ->name('catalog.attributes.update');
    Route::delete('/catalog/attributes/{attribute}', [AttributeController::class, 'destroy'])
        ->middleware('store.permission:catalog.manage')
        ->name('catalog.attributes.destroy');
    Route::post('/catalog/attributes/{attribute}/terms', [AttributeController::class, 'storeTerm'])
        ->middleware('store.permission:catalog.manage')
        ->name('catalog.attributes.terms.store');
    Route::get('/orders', [DashboardController::class, 'orders'])->name('orders');
    Route::get('/orders/create', [DraftOrderController::class, 'create'])
        ->middleware('store.permission:orders.manage')
        ->name('orders.create');
    Route::get('/orders/{order}', [DashboardController::class, 'orderViewDetails'])->name('orderViewDetails');
    Route::patch('/orders/{order}/status', [DashboardController::class, 'updateOrderStatus'])
        ->middleware('store.permission:orders.manage')
        ->name('orders.updateStatus');
    Route::post('/orders/{order}/notes', [OrderController::class, 'storeNote'])
        ->middleware('store.permission:orders.manage')
        ->name('orders.notes.store');
    Route::post('/orders/{order}/shipments', [ShipmentController::class, 'store'])
        ->middleware('store.permission:orders.manage')
        ->name('orders.shipments.store');
    Route::patch('/shipments/{shipment}/tracking', [ShipmentController::class, 'updateTracking'])
        ->middleware('store.permission:orders.manage')
        ->name('shipments.tracking.update');
    Route::post('/shipments/{shipment}/mark-shipped', [ShipmentController::class, 'markShipped'])
        ->middleware('store.permission:orders.manage')
        ->name('shipments.mark-shipped');
    Route::post('/shipments/{shipment}/mark-delivered', [ShipmentController::class, 'markDelivered'])
        ->middleware('store.permission:orders.manage')
        ->name('shipments.mark-delivered');
    Route::post('/shipments/{shipment}/mark-failed', [ShipmentController::class, 'markFailed'])
        ->middleware('store.permission:orders.manage')
        ->name('shipments.mark-failed');
    Route::post('/shipments/{shipment}/cancel', [ShipmentController::class, 'cancel'])
        ->middleware('store.permission:orders.manage')
        ->name('shipments.cancel');
    Route::post('/draft-orders', [DraftOrderController::class, 'store'])
        ->middleware('store.permission:orders.manage')
        ->name('draft-orders.store');
    Route::get('/draft-orders/{draftOrder}', [DraftOrderController::class, 'show'])
        ->middleware('store.permission:orders.manage')
        ->name('draft-orders.show');
    Route::match(['post', 'patch'], '/draft-orders/{draftOrder}', [DraftOrderController::class, 'update'])
        ->middleware('store.permission:orders.manage')
        ->name('draft-orders.update');
    Route::post('/draft-orders/{draftOrder}/convert', [DraftOrderController::class, 'convert'])
        ->middleware('store.permission:orders.manage')
        ->name('draft-orders.convert');
    Route::patch('/draft-orders/{draftOrder}/cancel', [DraftOrderController::class, 'cancel'])
        ->middleware('store.permission:orders.manage')
        ->name('draft-orders.cancel');
    Route::delete('/draft-orders/{draftOrder}', [DraftOrderController::class, 'destroy'])
        ->middleware('store.permission:orders.manage')
        ->name('draft-orders.destroy');

    Route::get('/customers', [DashboardController::class, 'customers'])->name('customers');
    Route::get('/customers/{customer}', [DashboardController::class, 'customersProfile'])->name('customersProfile');
    Route::post('/customers/{customer}/notes', [CustomerController::class, 'storeNote'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.notes.store');
    Route::post('/customers/{customer}/tags', [CustomerController::class, 'storeTag'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.tags.store');
    Route::delete('/customers/{customer}/tags/{customerTag}', [CustomerController::class, 'destroyTag'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.tags.destroy');
    Route::post('/customers/{customer}/addresses', [CustomerController::class, 'storeAddress'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.addresses.store');
    Route::patch('/customers/{customer}/addresses/{address}', [CustomerController::class, 'updateAddress'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.addresses.update');
    Route::post('/customers/{customer}/addresses/{address}/default', [CustomerController::class, 'makeDefaultAddress'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.addresses.default');
    Route::delete('/customers/{customer}/addresses/{address}', [CustomerController::class, 'destroyAddress'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.addresses.destroy');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'updateStatus'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.status.update');
    Route::patch('/customers/{customer}/marketing', [CustomerController::class, 'updateMarketing'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.marketing.update');
    Route::post('/customers/{customer}/metrics/recalculate', [CustomerController::class, 'recalculateMetrics'])
        ->middleware('store.permission:customers.manage')
        ->name('customers.metrics.recalculate');
    Route::get('/team-members', [TeamMemberController::class, 'index'])
        ->middleware('store.permission:team.view')
        ->name('team-members.index');
    Route::post('/team-members', [TeamMemberController::class, 'store'])
        ->middleware('store.permission:team.manage')
        ->name('team-members.store');
    Route::patch('/team-members/{user}', [TeamMemberController::class, 'updateRole'])
        ->middleware('store.permission:team.manage')
        ->name('team-members.update');
    Route::delete('/team-members/{user}', [TeamMemberController::class, 'destroy'])
        ->middleware('store.permission:team.manage')
        ->name('team-members.destroy');

    Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');
    Route::get('/notifications', [DashboardController::class, 'notifications'])->name('notifications');

    Route::get('/BillingSubscription', [DashboardController::class, 'billingSubscription'])
        ->middleware('store.permission:billing.view')
        ->name('billingSubscription');
    Route::get('/generalSettings', [DashboardController::class, 'generalSettings'])
        ->middleware('store.permission:settings.view')
        ->name('generalSettings');
    Route::get('/settings/locations', [LocationController::class, 'index'])
        ->middleware('store.permission:settings.view')
        ->name('settings.locations.index');
    Route::post('/settings/locations', [LocationController::class, 'store'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.locations.store');
    Route::patch('/settings/locations/{location}', [LocationController::class, 'update'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.locations.update');
    Route::post('/settings/locations/{location}/make-default', [LocationController::class, 'makeDefault'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.locations.make-default');
    Route::patch('/settings/locations/{location}/deactivate', [LocationController::class, 'deactivate'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.locations.deactivate');
    Route::get('/settings/payments', [PaymentSettingsController::class, 'index'])
        ->middleware('store.permission:settings.view')
        ->name('settings.payments.index');
    Route::post('/settings/payments/mode', [PaymentSettingsController::class, 'updateMode'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.mode');
    Route::post('/settings/payments/external-inventory', [PaymentSettingsController::class, 'updateExternalInventory'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.external-inventory');
    Route::post('/settings/payments/platform-payment-mode', [PaymentSettingsController::class, 'updatePlatformPaymentMode'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.platform-payment-mode');
    Route::post('/settings/payments/stripe/connect/test/start', [PaymentSettingsController::class, 'connectTest'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.connect.test');
    Route::post('/settings/payments/stripe/connect/live/start', [PaymentSettingsController::class, 'connectLive'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.connect.live');
    Route::get('/settings/payments/stripe/connect/{mode}/return', [PaymentSettingsController::class, 'connectReturn'])
        ->middleware('store.permission:settings.manage')
        ->where('mode', 'test|live')
        ->name('settings.payments.stripe.connect.return');
    Route::post('/settings/payments/stripe/connect/{account}/refresh', [PaymentSettingsController::class, 'refreshConnectAccount'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.connect.refresh');
    Route::post('/settings/payments/stripe/connect/{account}/status', [PaymentSettingsController::class, 'refreshConnectAccountStatus'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.connect.status');
    Route::post('/settings/payments/stripe/connect/{account}/disconnect', [PaymentSettingsController::class, 'disconnectConnectAccount'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.connect.disconnect');
    Route::post('/settings/payments/stripe/connect', [PaymentSettingsController::class, 'connect'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.connect');
    Route::get('/settings/payments/stripe/return', [PaymentSettingsController::class, 'stripeReturn'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.return');
    Route::get('/settings/payments/stripe/refresh/{account?}', [PaymentSettingsController::class, 'refreshOnboarding'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.refresh');
    Route::post('/settings/payments/stripe/status/{account?}', [PaymentSettingsController::class, 'status'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.status');
    Route::post('/settings/payments/stripe/disable/{account?}', [PaymentSettingsController::class, 'disable'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.payments.stripe.disable');
    Route::get('/shippingAutomation', [ShippingSettingsController::class, 'index'])
        ->middleware('store.permission:settings.view')
        ->name('shippingAutomation');
    Route::post('/settings/shipping/carrier-accounts', [ShippingSettingsController::class, 'storeCarrierAccount'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.carrier-accounts.store');
    Route::patch('/settings/shipping/carrier-accounts/{carrierAccount}', [ShippingSettingsController::class, 'updateCarrierAccount'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.carrier-accounts.update');
    Route::delete('/settings/shipping/carrier-accounts/{carrierAccount}', [ShippingSettingsController::class, 'destroyCarrierAccount'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.carrier-accounts.destroy');
    Route::post('/settings/shipping/zones', [ShippingSettingsController::class, 'storeZone'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.zones.store');
    Route::patch('/settings/shipping/zones/{shippingZone}', [ShippingSettingsController::class, 'updateZone'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.zones.update');
    Route::delete('/settings/shipping/zones/{shippingZone}', [ShippingSettingsController::class, 'destroyZone'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.zones.destroy');
    Route::post('/settings/shipping/methods', [ShippingSettingsController::class, 'storeMethod'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.methods.store');
    Route::patch('/settings/shipping/methods/{shippingMethod}', [ShippingSettingsController::class, 'updateMethod'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.methods.update');
    Route::delete('/settings/shipping/methods/{shippingMethod}', [ShippingSettingsController::class, 'destroyMethod'])
        ->middleware('store.permission:settings.manage')
        ->name('settings.shipping.methods.destroy');
    Route::get('/security', [DashboardController::class, 'security'])
        ->middleware('store.permission:security.view')
        ->name('security');
    Route::delete('/security/sessions/{userSession}', [DashboardController::class, 'revokeUserSession'])
        ->name('security.sessions.destroy');
    Route::get('/profileSettings', [DashboardController::class, 'profileSettings'])->name('profileSettings');
    Route::patch('/profileSettings', [DashboardController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profileSettings/password', [DashboardController::class, 'updatePassword'])->name('profile.password.update');
    Route::patch('/profileSettings/deactivate', [DashboardController::class, 'deactivateAccount'])->name('profile.deactivate');

    Route::get('/developer-storefront', [DeveloperStorefrontSettingsController::class, 'show'])
        ->middleware('store.permission:developer_api.view')
        ->name('developer-storefront.settings');
    Route::post('/developer-storefront/token', [DeveloperStorefrontSettingsController::class, 'generate'])
        ->middleware('store.permission:developer_api.manage')
        ->name('developer-storefront.token.generate');
    Route::delete('/developer-storefront/token', [DeveloperStorefrontSettingsController::class, 'revoke'])
        ->middleware('store.permission:developer_api.manage')
        ->name('developer-storefront.token.revoke');

    Route::get('/onboarding-StoreDetails-1', [OnboardingController::class, 'step1'])->name('onboarding-StoreDetails-1');
    Route::post('/onboarding-StoreDetails-1', [OnboardingController::class, 'storeStep1'])->name('onboarding-StoreDetails-1.store');
    Route::get('/AddCustomCategoryOverlay', [OnboardingController::class, 'customCategoryOverlay'])->name('AddCustomCategoryOverlay');
    Route::post('/AddCustomCategoryOverlay', [OnboardingController::class, 'storeCustomCategoryOverlay'])->name('AddCustomCategoryOverlay.store');
    Route::get('/onboarding-Step2-AddProductVariations', [OnboardingController::class, 'step2'])->name('onboarding-Step2-AddProductVariations');
    Route::post('/onboarding-Step2-AddProductVariations', [OnboardingController::class, 'storeStep2'])->name('onboarding-Step2-AddProductVariations.store');
    Route::get('/onboarding-Step2-VariationsPopup', [OnboardingController::class, 'variationPopup'])->name('onboarding_AddProduct_VariationsPopup');
    Route::post('/onboarding-Step2-VariationsPopup', [OnboardingController::class, 'storeVariationPopup'])->name('onboarding_AddProduct_VariationsPopup.store');
    Route::get('/onboarding-Step3-StoreReady', [OnboardingController::class, 'step3'])->name('onboarding_StoreReady');
    Route::post('/onboarding-Step3-StoreReady', [OnboardingController::class, 'completeStep3'])->name('onboarding_StoreReady.complete');
    Route::get('/store-management', [DashboardController::class, 'store_management'])->name('store-management');
    Route::post('/current-store', [CurrentStoreController::class, 'update'])->name('current-store.update');
    Route::put('/store/{storeId}', [OnboardingController::class, 'updateStoreFromManagement'])->name('store.update');
    Route::delete('/store/{storeId}', [OnboardingController::class, 'destroyStoreFromManagement'])->name('store.destroy');
    Route::post('/products', [OnboardingController::class, 'storeProductFromCurrentStore'])
        ->middleware('store.permission:catalog.manage')
        ->name('product.store');
    Route::put('/product/{productId}', [OnboardingController::class, 'updateProductFromManagement'])
        ->middleware('store.permission:catalog.manage')
        ->name('product.update');
    Route::delete('/product/{productId}', [OnboardingController::class, 'destroyProductFromManagement'])
        ->middleware('store.permission:catalog.manage')
        ->name('product.destroy');

    Route::get('/products/import/template', [ProductImportController::class, 'template'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.template');
    Route::get('/products/import/history', [ProductImportController::class, 'history'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.history');
    Route::get('/products/import', [ProductImportController::class, 'create'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.create');
    Route::post('/products/import', [ProductImportController::class, 'store'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.store');
    Route::get('/products/import/{productImportId}/mapping', [ProductImportController::class, 'mapping'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.mapping');
    Route::post('/products/import/{productImportId}/mapping', [ProductImportController::class, 'saveMapping'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.mapping.save');
    Route::post('/products/import/{productImportId}/reopen-mapping', [ProductImportController::class, 'reopenMapping'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.reopen-mapping');
    Route::get('/products/import/{productImportId}/preview', [ProductImportController::class, 'preview'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.preview');
    Route::post('/products/import/{productImportId}/confirm', [ProductImportController::class, 'confirm'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.confirm');
    Route::post('/products/import/{productImportId}/resume', [ProductImportController::class, 'resume'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.resume');
    Route::get('/products/import/{productImportId}/result', [ProductImportController::class, 'result'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.result');
    Route::get('/products/import/{productImportId}/progress', [ProductImportController::class, 'importProgress'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.progress');
    Route::get('/products/import/{productImportId}/report', [ProductImportController::class, 'report'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.report');
    Route::post('/products/import/{productImportId}/retry-failed', [ProductImportController::class, 'retryFailed'])
        ->middleware('store.permission:imports.manage')
        ->name('products.import.retry-failed');

    Route::get('/store/{storeId}/products', [DashboardController::class, 'store_products'])->name('store.products');
    Route::get('/store/{storeId}/add-product', [OnboardingController::class, 'addProductFromStore'])->name('store.add-product');
    Route::post('/store/{storeId}/add-product', [OnboardingController::class, 'storeProductFromStore'])->name('store.add-product.store');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin-dashboard', [AdminController::class, 'admin_dashboard'])->name('admin-dashboard');
    Route::get('/admin-tenant', [AdminController::class, 'admin_tenant'])->name('admin-tenant');
    Route::get('/admin-products', [AdminController::class, 'admin_products'])->name('admin-products');
    Route::get('/admin-users', [AdminController::class, 'admin_users'])->name('admin-users');

    Route::get('/admin-infrastructure', [AdminController::class, 'admin_infrastructure'])->name('admin-infrastructure');
    Route::get('/admin-ups', [AdminController::class, 'admin_ups'])->name('admin-ups');
    Route::get('/admin-add-logistic', [AdminController::class, 'admin_infrastructure_add_logistic'])->name('admin-infrastructure-add-logistic');

    Route::get('/admin-billing', [AdminController::class, 'admin_billing'])->name('admin-billing');

    Route::get('/admin-settings', [AdminController::class, 'admin_settings'])->name('admin-settings');
    Route::get('/admin-security', [AdminController::class, 'admin_settings_security'])->name('admin-security');
    Route::get('/admin-notifications', [AdminController::class, 'admin_settings_notifications'])->name('admin-notifications');
    Route::get('/admin-profile', [AdminController::class, 'admin_profile'])->name('admin-profile');
});
