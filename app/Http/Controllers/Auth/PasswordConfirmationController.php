<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordConfirmationController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.confirm-password', [
            'intendedUrl' => $request->session()->get('url.intended'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->input('password'), (string) $request->user()?->password)) {
            throw ValidationException::withMessages([
                'password' => __('The provided password is incorrect.'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('store-management'));
    }
}
