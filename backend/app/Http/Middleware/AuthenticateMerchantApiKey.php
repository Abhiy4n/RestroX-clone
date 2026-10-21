<?php

namespace App\Http\Middleware;

use App\Models\MerchantApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMerchantApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->bearerToken();
        $apiKey = $secret
            ? MerchantApiKey::query()
                ->with('merchant')
                ->where('secret_hash', hash('sha256', $secret))
                ->first()
            : null;

        if (! $apiKey?->isUsable()) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('authenticated_merchant', $apiKey->merchant);
        $request->attributes->set('authenticated_merchant_api_key', $apiKey);

        return $next($request);
    }
}
