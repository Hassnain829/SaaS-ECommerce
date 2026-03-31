<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CurrentStoreController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;
// 
Route::get('/', function () {
    return view('user_view.welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/signin', [DashboardController::class, 'signin'])->name('signin');
    Route::post('/signin', [DashboardController::class, 'authenticate'])->name('signin.attempt');
    Route::get('/register', [DashboardController::class, 'register'])->name('register');
});

Route::get('/logout', [DashboardController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:user', 'current.store'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/products', [DashboardController::class, 'product'])->name('products');
    Route::get('/orders', [DashboardController::class, 'orders'])->name('orders');
    Route::get('/orderViewDetails', [DashboardController::class, 'orderViewDetails'])->name('orderViewDetails');

    Route::get('/customers', [DashboardController::class, 'customers'])->name('customers');
    Route::get('/customersProfile', [DashboardController::class, 'customersProfile'])->name('customersProfile');

    Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');
    Route::get('/notifications', [DashboardController::class, 'notifications'])->name('notifications');

    Route::get('/BillingSubscription', [DashboardController::class, 'billingSubscription'])->name('billingSubscription');
    Route::get('/generalSettings', [DashboardController::class, 'generalSettings'])->name('generalSettings');
    Route::get('/shippingAutomation', [DashboardController::class, 'shippingAutomation'])->name('shippingAutomation');
    Route::get('/security', [DashboardController::class, 'security'])->name('security');
    Route::get('/profileSettings', [DashboardController::class, 'profileSettings'])->name('profileSettings');

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
    Route::put('/product/{productId}', [OnboardingController::class, 'updateProductFromManagement'])->name('product.update');
    Route::delete('/product/{productId}', [OnboardingController::class, 'destroyProductFromManagement'])->name('product.destroy');
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
