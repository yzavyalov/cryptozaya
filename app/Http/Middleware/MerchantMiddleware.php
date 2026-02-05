<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Merchant;

class MerchantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized: token missing'], 401);
        }

        $token = substr($header, 7);
        $hashedToken = hash('sha256', $token);

        $merchant = Merchant::whereNotNull('token')
            ->where('token', $hashedToken)
            ->first();

        if (!$merchant) {
            return response()->json(['error' => 'Unauthorized: invalid token'], 401);
        }

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }

}
