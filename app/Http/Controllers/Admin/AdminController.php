<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function admin_dashboard(): View
    {
        return $this->unavailable('Platform overview');
    }

    public function admin_tenant(): View
    {
        return $this->unavailable('Tenants');
    }

    public function admin_products(): View
    {
        return $this->unavailable('Products');
    }

    public function admin_users(): View
    {
        return $this->unavailable('Users');
    }

    public function admin_infrastructure(): View
    {
        return $this->unavailable('Infrastructure');
    }

    public function admin_ups(): View
    {
        return $this->unavailable('UPS');
    }

    public function admin_billing(): View
    {
        return $this->unavailable('Billing');
    }

    public function admin_settings(): View
    {
        return $this->unavailable('Settings');
    }

    public function admin_profile(): View
    {
        return $this->unavailable('Profile');
    }

    public function admin_settings_security(): View
    {
        return $this->unavailable('Security');
    }

    public function admin_settings_notifications(): View
    {
        return $this->unavailable('Notifications');
    }

    public function admin_infrastructure_add_logistic(): View
    {
        return $this->unavailable('Add logistic');
    }

    private function unavailable(string $pageLabel): View
    {
        return view('admin_view.unavailable', [
            'pageLabel' => $pageLabel,
        ]);
    }
}
