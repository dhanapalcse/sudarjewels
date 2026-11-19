<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Any signed-in user with an affiliate profile (pending affiliates see a limited dashboard). */
class EnsureAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active || ! $user->affiliate) {
            abort(403, 'This area is for SUDAR JEWELS affiliates.');
        }

        return $next($request);
    }
}
