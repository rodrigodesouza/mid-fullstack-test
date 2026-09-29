<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckRole
{
    public function handle(
        Request $request,
        Closure $next,
        string $role,
    ): Response {
        $user = $request->user();

        abort_if(! $user || $user->role !== $role, 403);

        return $next($request);
    }
}
