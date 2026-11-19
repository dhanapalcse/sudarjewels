<?php

namespace App\Http\Middleware;

use App\Domain\Affiliates\ReferralService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferral
{
    public function __construct(private ReferralService $referrals) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $this->referrals->captureFromRequest($request);
        }

        return $next($request);
    }
}
