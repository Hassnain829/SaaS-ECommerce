<?php

namespace App\Http\Middleware;

use App\Services\UserSessionTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RecordUserSession
{
    public function __construct(private readonly UserSessionTracker $tracker) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $this->tracker->currentSessionIsRevoked($request)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('signin')
                ->withErrors(['email' => 'This session was signed out. Please sign in again.']);
        }

        if ($request->user()) {
            $this->tracker->touch($request);
        }

        $response = $next($request);

        if ($request->user()) {
            $this->tracker->touch($request);
        }

        return $response;
    }
}
