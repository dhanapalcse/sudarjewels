<?php

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliates\ReferralService;
use App\Domain\Catalogue\CommissionRateResolver;
use App\Domain\Commissions\CommissionService;
use App\Enums\PayoutMethod;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchasedUnit;
use App\Support\Audit;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private function me(Request $request): Affiliate
    {
        return $request->user()->affiliate;
    }

    public function overview(Request $request, CommissionService $commissions): View
    {
        $me = $this->me($request);

        return view('affiliate.overview', [
            'affiliate' => $me,
            'balances' => $commissions->balances($me->id),
            'statusCounts' => $me->orders()->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status'),
            'recent' => $me->orders()->latest()->limit(5)->get(),
            'visits' => $me->visits()->where('created_at', '>=', now()->subDays(30))->count(),
            'seo' => Seo::make('Affiliate overview', null, ['noindex' => true]),
        ]);
    }

    public function catalogue(Request $request, CommissionRateResolver $rates): View
    {
        $q = mb_substr(trim((string) $request->query('q')), 0, 80);
        $products = Product::published()->with(['mainImage', 'activeVariants', 'category'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->orderBy('name')->paginate(24)->withQueryString();

        return view('affiliate.catalogue', ['products' => $products, 'q' => $q, 'rates' => $rates, 'affiliate' => $this->me($request), 'seo' => Seo::make('Catalogue', null, ['noindex' => true])]);
    }

    public function share(Request $request, ReferralService $referrals): View
    {
        $me = $this->me($request);
        $products = Product::published()->with('mainImage')->orderByDesc('is_featured')->orderBy('name')->paginate(24);

        return view('affiliate.share', ['products' => $products, 'affiliate' => $me, 'referrals' => $referrals, 'seo' => Seo::make('Share products', null, ['noindex' => true])]);
    }

    /** Only customers from this affiliate's own orders, with details as entered on those orders. */
    public function customers(Request $request): View
    {
        $me = $this->me($request);
        $rows = Order::where('affiliate_id', $me->id)
            ->select('customer_phone', DB::raw('max(customer_name) as customer_name'), DB::raw('count(*) as orders_count'), DB::raw('max(created_at) as last_order_at'))
            ->groupBy('customer_phone')->orderByDesc('last_order_at')->paginate(25);

        return view('affiliate.customers', ['customers' => $rows, 'seo' => Seo::make('My customers', null, ['noindex' => true])]);
    }

    public function earnings(Request $request, CommissionService $commissions): View
    {
        $me = $this->me($request);

        return view('affiliate.earnings', [
            'balances' => $commissions->balances($me->id),
            'entries' => $me->commissionEntries()->with('order:id,order_number')->latest('id')->paginate(30),
            'seo' => Seo::make('My earnings', null, ['noindex' => true]),
        ]);
    }

    public function payouts(Request $request): View
    {
        return view('affiliate.payouts', [
            'payouts' => $this->me($request)->payouts()->latest()->paginate(20),
            'seo' => Seo::make('Payout history', null, ['noindex' => true]),
        ]);
    }

    public function warranty(Request $request): View
    {
        $units = PurchasedUnit::with(['orderItem', 'order:id,order_number,customer_name'])
            ->whereHas('order', fn ($q) => $q->where('affiliate_id', $this->me($request)->id))
            ->where('warranty_eligible', true)->latest('id')->paginate(30);

        return view('affiliate.warranty', ['units' => $units, 'seo' => Seo::make('Warranty status', null, ['noindex' => true])]);
    }

    public function profile(Request $request): View
    {
        return view('affiliate.profile', ['affiliate' => $this->me($request)->load('user'), 'seo' => Seo::make('Profile', null, ['noindex' => true])]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $me = $this->me($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:100'],
            'social_handle' => ['nullable', 'string', 'max:160'],
            'payout_method' => ['nullable', Rule::enum(PayoutMethod::class)],
            'payout_account_name' => ['nullable', 'required_if:payout_method,bank_transfer', 'string', 'max:120'],
            'payout_account_number' => ['nullable', 'regex:/^\d{6,18}$/'],
            'payout_ifsc' => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'payout_upi_id' => ['nullable', 'regex:/^[a-zA-Z0-9._-]{2,64}@[a-zA-Z]{2,32}$/'],
        ], ['payout_ifsc.regex' => 'Enter a valid 11-character IFSC code.', 'payout_upi_id.regex' => 'Enter a valid UPI ID (name@bank).']);

        $request->user()->update(['name' => $data['name']]);
        $me->fill(['city' => $data['city'] ?? null, 'social_handle' => $data['social_handle'] ?? null]);

        // Payout details are only replaced when new values are entered (stored values are never echoed back).
        $payoutChanged = false;
        if (! empty($data['payout_method'])) {
            $me->payout_method = $data['payout_method'];
            foreach (['payout_account_name', 'payout_account_number', 'payout_ifsc', 'payout_upi_id'] as $field) {
                if (filled($data[$field] ?? null)) {
                    $me->{$field} = $data[$field];
                    $payoutChanged = true;
                }
            }
            if ($data['payout_method'] === 'bank_transfer' && (! $me->payout_account_number || ! $me->payout_ifsc)) {
                return back()->withInput()->withErrors(['payout_account_number' => 'Enter the account number and IFSC for bank transfers.']);
            }
            if ($data['payout_method'] === 'upi' && ! $me->payout_upi_id) {
                return back()->withInput()->withErrors(['payout_upi_id' => 'Enter your UPI ID.']);
            }
        }
        if ($payoutChanged) {
            $me->payout_details_updated_at = now();
            Audit::log('affiliate.payout_details_changed', $me, ['payout_method' => $me->payout_method?->value]);
        }
        $me->save();

        return back()->with('success', 'Profile saved.');
    }

    public function help(): View
    {
        return view('affiliate.help', ['seo' => Seo::make('Help', null, ['noindex' => true])]);
    }
}
