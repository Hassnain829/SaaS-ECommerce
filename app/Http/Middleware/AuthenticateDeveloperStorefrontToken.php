<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeveloperStorefrontToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Missing or invalid Bearer token.'], 401);
        }

        $hash = hash('sha256', $token);

        $store = Store::query()
            ->where('developer_storefront_token_hash', $hash)
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Invalid storefront token.'], 401);
        }

        $request->attributes->set('developerStorefrontStore', $store);

        return $next($request);
    }
}
