<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function signin(): RedirectResponse|\Illuminate\View\View
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('user_view.signin');
    }

    public function register(): RedirectResponse|\Illuminate\View\View
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('user_view.register');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'email' => 'Email or password is incorrect.',
            ])->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return $this->redirectByRole();
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('register');
    }

    private function redirectByRole(): RedirectResponse
    {
        $role = Auth::user()?->role?->name;

        if ($role === 'admin') {
            return redirect()->route('admin-dashboard');
        }

        return redirect()->route('dashboard');
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

    public function onboarding_StoreDetails_1()
    {
        return view('user_view.onboarding-Step1-StoreDetails');
    }

    public function onboarding_AddCustom_Category()
    {
        return view('user_view.onboarding-Step1-addCustom-Category');
    }

    public function onboarding_AddProduct_Variations()
    {
        return view('user_view.onboarding-Step2-AddProductVariations');
    }

    public function onboarding_AddProduct_VariationsPopup()
    {
        return view('user_view.onboarding-Step2-AddVariationPopup');
    }

    public function onboarding_StoreReady()
    {
        return view('user_view.onboarding-Step3-StoreReady');
    }
    public function store_management(){
        return view('user_view.store_management');
    }
}
