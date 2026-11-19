<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PurchasedUnit;
use App\Models\WarrantyRegistration;
use App\Support\Audit;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    private function customer(Request $request): Customer
    {
        return $request->user('customer');
    }

    private function visibleOrders(Customer $customer)
    {
        return Order::where('customer_id', $customer->id)->whereNotIn('status', [OrderStatus::Draft->value]);
    }

    public function dashboard(Request $request): View
    {
        $customer = $this->customer($request);

        return view('account.dashboard', [
            'customer' => $customer,
            'orders' => $this->visibleOrders($customer)->latest()->limit(3)->get(),
            'warrantyCount' => PurchasedUnit::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))->where('warranty_status', 'registered')->count(),
            'openClaims' => $customer->claims()->whereNotIn('status', ['rejected', 'resolved'])->count(),
            'seo' => Seo::make('My account', null, ['noindex' => true]),
        ]);
    }

    public function orders(Request $request): View
    {
        return view('account.orders', [
            'orders' => $this->visibleOrders($this->customer($request))->withCount('items')->latest()->paginate(10),
            'seo' => Seo::make('My orders', null, ['noindex' => true]),
        ]);
    }

    public function order(Request $request, Order $order): View
    {
        abort_unless($order->customer_id === $this->customer($request)->id && $order->status !== OrderStatus::Draft, 404);
        $order->load(['items.units', 'shipments', 'warrantyRegistration']);

        return view('account.order', ['order' => $order, 'seo' => Seo::make('Order '.$order->order_number, null, ['noindex' => true])]);
    }

    public function warranties(Request $request): View
    {
        $customer = $this->customer($request);
        $units = PurchasedUnit::with(['orderItem', 'order', 'registration', 'claims'])
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereIn('warranty_status', ['registered', 'unregistered', 'void'])
            ->where('warranty_eligible', true)
            ->orderByDesc('id')->get();

        return view('account.warranties', [
            'units' => $units,
            'registrations' => $customer->warrantyRegistrations()->with('order')->get()
                ->merge(WarrantyRegistration::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))->get())->unique('id'),
            'seo' => Seo::make('My warranties', null, ['noindex' => true]),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('account.profile', ['customer' => $this->customer($request), 'seo' => Seo::make('Profile', null, ['noindex' => true])]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);
        $optIn = (bool) ($data['marketing_opt_in'] ?? false);
        $consentChanged = $optIn !== $customer->marketing_opt_in;
        $customer->fill(['name' => $data['name'], 'email' => $data['email'] ?? null, 'marketing_opt_in' => $optIn]);
        if ($consentChanged) {
            $customer->marketing_consent_at = now();
            $customer->marketing_consent_source = 'customer_portal';
            Audit::log('customer.marketing_consent', $customer, ['marketing_opt_in' => $optIn], null, $customer);
        }
        $customer->save();

        return back()->with('success', 'Your profile has been updated.');
    }
}
