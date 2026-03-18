<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// Authentication Routes for Users
Route::get('signin',[DashboardController::class,'signin'])->name('signin');

Route::get('register',[DashboardController::class,'register'])->name('register'); 

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/products', [DashboardController::class, 'product'])->name('products');

Route::get('/orders', [DashboardController::class, 'orders'])->name('orders');

Route::get('/customers', [DashboardController::class,'customers'])->name('customers');

Route::get('/customersProfile', [DashboardController::class,'customersProfile'])->name(  'customersProfile');

Route::get('/analytics', [DashboardController::class,'analytics'])->name(  'analytics');

Route::get('/notifications', [DashboardController::class,'notifications'])->name(  'notifications');

Route::get('/BillingSubscription',[DashboardController::class,'billingSubscription'])->name('billingSubscription');

Route::get('/generalSettings', [DashboardController::class,'generalSettings'])->name('generalSettings');

Route::get('/shippingAutomation', [DashboardController::class,'shippingAutomation'])->name('shippingAutomation');

Route::get('/security', [DashboardController::class,'security'])->name(  'security');

Route::get('/profileSettings', [DashboardController::class,'profileSettings'])->name('profileSettings');

Route::get('/orderViewDetails', [DashboardController::class,'orderViewDetails'])->name('orderViewDetails');

Route::get('/onboarding-StoreDetails-1', [DashboardController::class,'onboarding_StoreDetails_1'])->name(  'onboarding-StoreDetails-1');

Route::get('/AddCustomCategoryOverlay', [DashboardController::class,'onboarding_AddCustom_Category'])->name('AddCustomCategoryOverlay'); 

Route::get('/onboarding-Step2-AddProductVariations', [DashboardController::class,'onboarding_AddProduct_Variations'])->name('onboarding-Step2-AddProductVariations');

Route::get('/onboarding-Step2-VariationsPopup', [DashboardController::class,'onboarding_AddProduct_VariationsPopup'])->name(  'onboarding_AddProduct_VariationsPopup');

Route::get('/onboarding-Step3-StoreReady', [DashboardController::class,'onboarding_StoreReady'])->name('onboarding_StoreReady');


// Admin Routes
Route::get('/admin-dashboard', [App\Http\Controllers\AdminController::class, 'admin_dashboard'])->name('admin-dashboard');

Route::get('/admin-tenant', [App\Http\Controllers\AdminController::class, 'admin_tenant'])->name('admin-tenant');

Route::get('/admin-products', [App\Http\Controllers\AdminController::class, 'admin_products'])->name('admin-products');

Route::get('/admin-users', [App\Http\Controllers\AdminController::class, 'admin_users'])->name('admin-users');  

Route::get('/admin-infrastructure', [App\Http\Controllers\AdminController::class, 'admin_infrastructure'])->name('admin-infrastructure');

Route::get('/admin-ups', [App\Http\Controllers\AdminController::class, 'admin_ups'])->name('admin-ups');

Route::get('/admin-billing', [App\Http\Controllers\AdminController::class, 'admin_billing'])->name('admin-billing');

Route::get('/admin-settings', [App\Http\Controllers\AdminController::class, 'admin_settings'])->name('admin-settings');

Route::get('/admin-profile', [App\Http\Controllers\AdminController::class, 'admin_profile'])->name('admin-profile');    

Route::get('/admin-add-logistic', [App\Http\Controllers\AdminController::class, 'admin_infrastructure_add_logistic'])->name('admin-infrastructure-add-logistic');

Route::get('/admin-security', [App\Http\Controllers\AdminController::class, 'admin_settings_security'])->name('admin-security');

Route::get('/admin-notifications', [App\Http\Controllers\AdminController::class, 'admin_settings_notifications'])->name('admin-notifications');