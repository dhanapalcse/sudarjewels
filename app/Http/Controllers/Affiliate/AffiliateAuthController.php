<?php

namespace App\Http\Controllers\Affiliate;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\User;
use App\Support\Audit;
use App\Support\Permissions;
use App\Support\Phone;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AffiliateAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('affiliate.auth.login', ['seo' => Seo::make('Affiliate login', null, ['noindex' => true])]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These details do not match an active affiliate account.']);
        }
        $user = $request->user();
        if (! $user->affiliate) {
            Auth::logout();
            $request->session()->invalidate();
            if ($user->isStaff()) {
                return redirect('/admin/login')->with('status', 'Staff sign in to the admin dashboard.');
            }
            throw ValidationException::withMessages(['email' => 'These details do not match an active affiliate account.']);
        }
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('affiliate.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('affiliate.login');
    }

    public function showApply(): View
    {
        return view('affiliate.auth.apply', ['seo' => Seo::make('Become an affiliate', 'Apply to earn commission by sharing SUDAR JEWELS.')]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'social_handle' => ['nullable', 'string', 'max:160'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Please accept the affiliate terms.']);
        $phone = Phone::normalize($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Enter a valid 10-digit mobile number.']);

        $user = DB::transaction(function () use ($data, $phone) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'phone' => $phone, 'password' => $data['password']]);
            $user->assignRole(Permissions::AFFILIATE);
            do {
                $code = 'SUDAR'.random_int(100, 99999);
            } while (Affiliate::where('code', $code)->exists());
            $affiliate = Affiliate::create([
                'user_id' => $user->id, 'code' => $code, 'status' => AffiliateStatus::Pending, 'phone' => $phone,
                'city' => $data['city'] ?? null, 'social_handle' => $data['social_handle'] ?? null, 'terms_accepted_at' => now(),
            ]);
            Audit::log('affiliate.applied', $affiliate, [], null, $user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('affiliate.dashboard')->with('success', 'Thanks for applying! We will review your application and activate your account.');
    }
}
