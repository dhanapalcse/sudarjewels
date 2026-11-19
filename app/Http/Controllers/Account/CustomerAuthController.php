<?php

namespace App\Http\Controllers\Account;

use App\Domain\Auth\OtpChallenge;
use App\Domain\Auth\OtpService;
use App\Domain\Notifications\Sms\TestSmsGateway;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Mask;
use App\Support\Phone;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    private const SESSION = 'customer_login';

    public function showLogin(): View
    {
        return view('account.login', ['seo' => Seo::make('Sign in', null, ['noindex' => true])]);
    }

    public function sendCode(Request $request, OtpChallenge $challenge): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = Phone::normalize($request->input('phone'));
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid 10-digit mobile number.']);
        }
        $exists = Customer::where('phone', $phone)->exists();
        $challenge->begin($phone, OtpPurpose::CustomerLogin, $exists);
        $request->session()->put(self::SESSION, ['phone' => $phone, 'failures' => 0]);

        return redirect()->route('customer.login.verify');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $flow = $request->session()->get(self::SESSION);
        if (! $flow) {
            return redirect()->route('customer.login');
        }

        return view('account.verify', [
            'maskedPhone' => Mask::phone($flow['phone']),
            'testOtp' => TestSmsGateway::peek($flow['phone']),
            'seo' => Seo::make('Enter code', null, ['noindex' => true]),
        ]);
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        $flow = $request->session()->get(self::SESSION);
        if (! $flow) {
            return redirect()->route('customer.login');
        }
        $request->validate(['code' => ['required', 'digits:'.config('sudar.otp.length')]]);

        $customer = Customer::where('phone', $flow['phone'])->first();
        if (! $customer || ! $otp->verify($flow['phone'], OtpPurpose::CustomerLogin, (string) $request->input('code'))) {
            $flow['failures']++;
            if ($flow['failures'] >= config('sudar.otp.max_attempts')) {
                $request->session()->forget(self::SESSION);

                return redirect()->route('customer.login')->with('error', 'Too many incorrect attempts. Please request a new code.');
            }
            $request->session()->put(self::SESSION, $flow);
            throw ValidationException::withMessages(['code' => 'That code is incorrect or has expired.']);
        }

        $request->session()->forget(self::SESSION);
        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();
        $customer->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('account.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
