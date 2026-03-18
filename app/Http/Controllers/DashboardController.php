<?php

namespace App\Http\Controllers;


class DashboardController extends Controller
{
    public function signin()
    {
        return view("user_view.signin");
    }
    public function register()
    {
        return view("user_view.register");

    }
    public function index()
    {
        return view('user_view.dashboard');
    }

    public function product()
    {
        return view('user_view.products');
    }

    public function orders()
    {
        return view('user_view.orders');
    }
    public function orderViewDetails()
    {
        return view('user_view.orderViewDetails');
    }

    public function customers()
    {
        return view('user_view.customers');
    }
    public function customersProfile()
    {
        return view('user_view.customersProfileTab');
    }
    public function analytics()
    {
        return view('user_view.analytics');
    }
    public function notifications()
    {
        return view('user_view.notifications');
    }
    public function billingSubscription()
    {
        return view('user_view.billingSubscription');
    }
    public function generalSettings()
    {
        return view('user_view.generalSettings');
    }

    public function shippingAutomation()
    {
        return view('user_view.shippingAutomation');
    }

    public function security()
    {
        return view('user_view.security');
    }
    public function profileSettings()
    {
        return view('user_view.profileSettings');
    }

    public function onboarding_StoreDetails_1(){
        return view('user_view.onboarding-Step1-StoreDetails');
    }
    public function onboarding_AddCustom_Category(){
        return view('user_view.onboarding-Step1-addCustom-Category');
    }
    public function onboarding_AddProduct_Variations(){
        return view('user_view.onboarding-Step2-AddProductVariations');
    }
    public function onboarding_AddProduct_VariationsPopup(){
        return view('user_view.onboarding-Step2-AddVariationPopup');
    }
    public function onboarding_StoreReady(){
        return view('user_view.onboarding-Step3-StoreReady');
    }


}
