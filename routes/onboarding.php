<?php

use App\Http\Controllers\Store\ClosedStoreManagementController;
use App\Http\Controllers\Store\OnboardingController;
use Illuminate\Support\Facades\Route;

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
Route::put('/store/{storeId}', [OnboardingController::class, 'updateStoreFromManagement'])->name('store.update');
Route::patch('/store/{storeId}/lifecycle', [OnboardingController::class, 'updateStoreLifecycleFromManagement'])->name('store.lifecycle');
Route::delete('/store/{storeId}', [OnboardingController::class, 'destroyStoreFromManagement'])->name('store.destroy');
Route::get('/management/stores/closed', [ClosedStoreManagementController::class, 'index'])->name('stores.closed');
Route::post('/management/stores/{storeId}/restore', [ClosedStoreManagementController::class, 'restore'])->name('store.restore');
Route::delete('/management/stores/{storeId}/permanent', [ClosedStoreManagementController::class, 'permanentDestroy'])
    ->middleware('password.confirm')
    ->name('store.permanent-destroy');
Route::post('/products', [OnboardingController::class, 'storeProductFromCurrentStore'])
    ->middleware('store.permission:catalog.manage')
    ->name('product.store');
Route::put('/product/{productId}', [OnboardingController::class, 'updateProductFromManagement'])
    ->middleware('store.permission:catalog.manage')
    ->name('product.update');
Route::delete('/product/{productId}', [OnboardingController::class, 'destroyProductFromManagement'])
    ->middleware('store.permission:catalog.manage')
    ->name('product.destroy');
Route::post('/product/{productId}/restore', [OnboardingController::class, 'restoreProductFromManagement'])
    ->middleware('store.permission:catalog.manage')
    ->name('product.restore');
Route::delete('/product/{productId}/force', [OnboardingController::class, 'forceDestroyProductFromManagement'])
    ->middleware('store.permission:catalog.manage')
    ->name('product.force-destroy');
Route::get('/store/{storeId}/add-product', [OnboardingController::class, 'addProductFromStore'])->name('store.add-product');
Route::post('/store/{storeId}/add-product', [OnboardingController::class, 'storeProductFromStore'])->name('store.add-product.store');
