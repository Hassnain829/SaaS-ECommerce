<?php

namespace App\Http\Controllers;


class DashboardController extends Controller
{
    public function signin()
    {
        return view("signin");
    }
    public function register()
    {
        return view("register");

    }
    public function index()
    {
        return view('dashboard');
    }

    public function product()
    {
        return view('products');
    }

    public function orders()
    {
        return view('orders');
    }
    public function orderViewDetails()
    {
        return view('orderViewDetails');
    }

    public function customers()
    {
        return view('customers');
    }
    public function customersProfile()
    {
        return view('customersProfileTab');
    }
    public function analytics()
    {
        return view('analytics');
    }
    public function notifications()
    {
        return view('notifications');
    }
    public function billingSubscription()
    {
        return view('billingSubscription');
    }
    public function generalSettings()
    {
        return view('generalSettings');
    }

    public function shippingAutomation()
    {
        return view('shippingAutomation');
    }

    public function security()
    {
        return view('security');
    }
    public function profileSettings()
    {
        return view('profileSettings');
    }

    public function onboarding_StoreDetails_1(){
        return view('onboarding-Step1-StoreDetails');
    }
    public function onboarding_AddCustom_Category(){
        return view('onboarding-Step1-addCustom-Category');
    }
    public function onboarding_AddProduct_Variations(){
        return view('onboarding-Step2-AddProductVariations');
    }
    public function onboarding_AddProduct_VariationsPopup(){
        return view('onboarding-Step2-AddVariationPopup');
    }
    public function onboarding_StoreReady(){
        return view('onboarding-Step3-StoreReady');
    }


}
