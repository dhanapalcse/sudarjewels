<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\EnsureActiveAffiliate;
use App\Http\Middleware\EnsureAffiliate;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'referral' => CaptureReferral::class,
            'noindex' => NoIndex::class,
            'affiliate' => EnsureAffiliate::class,
            'affiliate.active' => EnsureActiveAffiliate::class,
        ]);
        $middleware->web(append: [SecurityHeaders::class]);
        // The referral cookie is JSON written by ReferralService; keep it encrypted (default).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('account*') ? route('customer.login') : route('affiliate.login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('account*') ? route('account.dashboard') : route('affiliate.dashboard'));
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Never write OTPs, passwords or tokens into logs via request input.
        $exceptions->dontFlash(['password', 'password_confirmation', 'otp', 'code', 'current_password']);
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            if (! $request->is('livewire*')) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        });
    })->create();
