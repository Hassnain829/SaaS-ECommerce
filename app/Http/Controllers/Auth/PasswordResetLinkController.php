<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim((string) $request->input('email')));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if (! $user?->must_set_password) {
            try {
                Password::sendResetLink(['email' => $user?->email ?? $email]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return back()->with(
            'status',
            'If an account exists for that email, password reset instructions have been sent.'
        );
    }
}
