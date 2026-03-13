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
}