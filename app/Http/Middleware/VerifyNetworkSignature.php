<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

final class VerifyNetworkSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Network-Timestamp');
        $signature = $request->header('X-Network-Signature');

        if ($timestamp === null || $signature === null) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! ctype_digit($timestamp)) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (abs(Date::now()->getTimestamp() - (int) $timestamp) > 300) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $timestamp.'.'.$request->getContent();

        $expectedSignature = 'sha256='.hash_hmac(
            'sha256',
            $payload,
            (string) config('services.network.secret'),
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
