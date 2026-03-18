<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/signin', [DashboardController::class, 'signin'])->name('signin');
    Route::post('/signin', [DashboardController::class, 'authenticate'])->name('signin.attempt');
    Route::get('/register', [DashboardController::class, 'register'])->name('register');
});

Route::get('/logout', [DashboardController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:user'])->group(function () {
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

    Route::get('/onboarding-StoreDetails-1', [DashboardController::class, 'onboarding_StoreDetails_1'])->name('onboarding-StoreDetails-1');
    Route::get('/AddCustomCategoryOverlay', [DashboardController::class, 'onboarding_AddCustom_Category'])->name('AddCustomCategoryOverlay');
    Route::get('/onboarding-Step2-AddProductVariations', [DashboardController::class, 'onboarding_AddProduct_Variations'])->name('onboarding-Step2-AddProductVariations');
    Route::get('/onboarding-Step2-VariationsPopup', [DashboardController::class, 'onboarding_AddProduct_VariationsPopup'])->name('onboarding_AddProduct_VariationsPopup');
    Route::get('/onboarding-Step3-StoreReady', [DashboardController::class, 'onboarding_StoreReady'])->name('onboarding_StoreReady');
    Route::get('/store-management', [DashboardController::class, 'store_management'])->name('store-management');
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
