<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Your email is already verified.');
        }

        try {
            $request->user()->notifyNow(new QueuedVerifyEmail);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with(
                'status',
                'We could not send the verification email right now. Please try again in a few minutes.'
            );
        }

        return back()->with('status', 'A new verification link has been sent to your email address.');
    }
}
