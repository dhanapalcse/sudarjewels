<?php

namespace App\Http\Controllers\Warranty;

use App\Domain\Auth\OtpChallenge;
use App\Domain\Auth\OtpService;
use App\Domain\Notifications\Sms\TestSmsGateway;
use App\Domain\Warranty\WarrantyService;
use App\Enums\OrderStatus;
use App\Enums\OtpPurpose;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\WarrantyRegistration;
use App\Support\Mask;
use App\Support\Phone;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Customer self-service warranty registration:
 * order number + name + phone → OTP (only if they match) → purchase details → register → card.
 */
class WarrantyController extends Controller
{
    private const SESSION = 'warranty_flow';

    private const VERIFIED_TTL_MINUTES = 30;

    public function start(): View
    {
        return view('warranty.start', ['seo' => Seo::make('Register your warranty', 'Register your SUDAR JEWELS three-month warranty with your order number and phone.', ['noindex' => false])]);
    }

    public function lookup(Request $request, OtpChallenge $challenge): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
        ], [], ['order_number' => 'order number']);

        $phone = Phone::normalize($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Enter the 10-digit mobile number used for the purchase.']);
        }
        $orderNumber = strtoupper(preg_replace('/\s+/', '', $data['order_number']));
        $order = Order::where('order_number', $orderNumber)->where('customer_phone', $phone)
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Completed->value])->first();

        $challenge->begin($phone, OtpPurpose::WarrantyLookup, $order !== null);

        $request->session()->put(self::SESSION, [
            'order_id' => $order?->id,
            'phone' => $phone,
            'name' => trim($data['name']),
            'verified_at' => null,
            'failures' => 0,
        ]);

        return redirect()->route('warranty.verify');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $flow = $request->session()->get(self::SESSION);
        if (! $flow) {
            return redirect()->route('warranty.start');
        }

        return view('warranty.verify', [
            'maskedPhone' => Mask::phone($flow['phone']),
            'testOtp' => TestSmsGateway::peek($flow['phone']),
            'seo' => Seo::make('Verify your phone', null, ['noindex' => true]),
        ]);
    }

    public function resend(Request $request, OtpChallenge $challenge): RedirectResponse
    {
        $flow = $request->session()->get(self::SESSION) ?? abort(419);
        $challenge->begin($flow['phone'], OtpPurpose::WarrantyLookup, $flow['order_id'] !== null);

        return back()->with('status', 'If the details match an order, a new code has been sent.');
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        $flow = $request->session()->get(self::SESSION);
        if (! $flow) {
            return redirect()->route('warranty.start');
        }
        $request->validate(['code' => ['required', 'digits:'.config('sudar.otp.length')]]);

        $ok = $flow['order_id'] !== null && $otp->verify($flow['phone'], OtpPurpose::WarrantyLookup, (string) $request->input('code'));
        if (! $ok) {
            $flow['failures']++;
            if ($flow['failures'] >= config('sudar.otp.max_attempts')) {
                $request->session()->forget(self::SESSION);

                return redirect()->route('warranty.start')->with('error', 'Too many incorrect attempts. Please start again.');
            }
            $request->session()->put(self::SESSION, $flow);
            throw ValidationException::withMessages(['code' => 'That code is incorrect or has expired.']);
        }

        $request->session()->regenerate();
        $flow['verified_at'] = now()->timestamp;
        $request->session()->put(self::SESSION, $flow);

        return redirect()->route('warranty.confirm');
    }

    public function confirmForm(Request $request, WarrantyService $warranty): View|RedirectResponse
    {
        $order = $this->verifiedOrder($request);
        if (! $order) {
            return redirect()->route('warranty.start')->with('error', 'Please verify your details first.');
        }
        $order->load(['items.units', 'warrantyRegistration']);

        return view('warranty.confirm', [
            'order' => $order,
            'flow' => $request->session()->get(self::SESSION),
            'eligible' => $warranty->eligibleUnits($order),
            'existing' => $order->warrantyRegistration,
            'seo' => Seo::make('Confirm warranty registration', null, ['noindex' => true]),
        ]);
    }

    public function register(Request $request, WarrantyService $warranty): RedirectResponse
    {
        $order = $this->verifiedOrder($request);
        if (! $order) {
            return redirect()->route('warranty.start')->with('error', 'Your verification has expired. Please start again.');
        }
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Please confirm the purchase details.']);
        $flow = $request->session()->get(self::SESSION);

        $customer = Customer::where('phone', $flow['phone'])->first();
        $registration = $warranty->register($order, $flow['name'], $flow['phone'], $customer);

        // The phone was verified by OTP, so the customer is signed in to their portal.
        if ($customer && ! Auth::guard('customer')->check()) {
            Auth::guard('customer')->login($customer);
            $customer->forceFill(['last_login_at' => now()])->save();
        }

        return redirect()->route('warranty.card', $registration)
            ->with('success', $registration->wasRecentlyCreated ? 'Your warranty is registered.' : 'This order was already registered — here is your warranty card.');
    }

    public function card(Request $request, WarrantyRegistration $registration): View|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        $sessionOrder = $this->verifiedOrder($request);
        $allowed = ($customer && $registration->order->customer_id === $customer->id) || ($sessionOrder && $sessionOrder->id === $registration->order_id);
        if (! $allowed) {
            return redirect()->route('warranty.start')->with('error', 'Please verify your details to view this warranty card.');
        }
        $registration->load(['order', 'units.orderItem', 'units.replacementOf']);

        return view('warranty.card', [
            'registration' => $registration,
            'pending' => $registration->order->units()->where('warranty_status', 'unregistered')->whereIn('status', ['allocated', 'dispatched'])->count(),
            'seo' => Seo::make('Warranty card', null, ['noindex' => true]),
        ]);
    }

    private function verifiedOrder(Request $request): ?Order
    {
        $flow = $request->session()->get(self::SESSION);
        if (! $flow || ! $flow['order_id'] || ! $flow['verified_at']) {
            return null;
        }
        if ($flow['verified_at'] < now()->subMinutes(self::VERIFIED_TTL_MINUTES)->timestamp) {
            $request->session()->forget(self::SESSION);

            return null;
        }

        return Order::find($flow['order_id']);
    }
}
