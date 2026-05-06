<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrentStoreController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperStorefrontSettingsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductBulkController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductWorkspaceController;
use App\Http\Controllers\ProductWorkspaceDataController;
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
    Route::get('/orders', [DashboardController::class, 'orders'])->name('orders');
    Route::get('/orders/{order}', [DashboardController::class, 'orderViewDetails'])->name('orderViewDetails');
    Route::patch('/orders/{order}/status', [DashboardController::class, 'updateOrderStatus'])
        ->middleware('store.permission:orders.manage')
        ->name('orders.updateStatus');

    Route::get('/customers', [DashboardController::class, 'customers'])->name('customers');
    Route::get('/customers/{customer}', [DashboardController::class, 'customersProfile'])->name('customersProfile');
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
    Route::get('/shippingAutomation', [DashboardController::class, 'shippingAutomation'])->name('shippingAutomation');
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
