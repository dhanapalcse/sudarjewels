<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only approved, active affiliates may create or edit orders. */
class EnsureActiveAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->affiliate?->isActive()) {
            if ($request->expectsJson()) {
                abort(403, 'Your affiliate account must be approved and active to create orders.');
            }

            return redirect()->route('affiliate.dashboard')
                ->with('error', 'Your affiliate account must be approved and active before you can create orders.');
        }

        return $next($request);
    }
}
