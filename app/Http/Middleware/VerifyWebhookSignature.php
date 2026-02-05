<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');
        $secret = env('WEBHOOK_SECRET');

        if (!is_string($signature) || $signature === '') {
            return response()->json([
                'error' => 'Signature missing'
            ], 401);
        }

        if (!is_string($secret) || $secret === '') {
            return response()->json([
                'error' => 'Server misconfigured'
            ], 500);
        }

        $payload = $request->getContent() ?: '';

        $expected = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json([
                'error' => 'Invalid signature'
            ], 401);
        }

        return $next($request);
    }
}
