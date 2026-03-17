<?php

namespace App\Http\Controllers;

class AdminController extends Controller
{
    public function admin_dashboard()
    {
        return view('admin_view.admin-dashboard');
    }
    public function admin_tenant(){
        return view('admin_view.admin-tenant');
    }

    public function admin_products(){
        return view('admin_view.admin-products');
    }
    public function admin_users(){
        return view('admin_view.admin_users');
    }
    public function admin_infrastructure(){
        return view('admin_view.admin-infrastructure');
    }
    public function admin_ups(){
        return view('admin_view.admin_infrastructure_UPS');
    }
    public function admin_billing(){
        return view('admin_view.admin_billing');
    }

    public function admin_settings(){
        return view('admin_view.admin_settings');
    }
    public function admin_profile(){
        return view('admin_view.admin_profile');
    }
    public function admin_infrastructure_add_logistic(){
        return view('admin_view.admin_infrastucture_add_logistic');
    }

    public function admin_settings_security(){
        return view('admin_view.admin_settings_security&Auth');
    }
    public function admin_settings_notifications(){
        return view('admin_view.admin_settings_notification');
    }
}        