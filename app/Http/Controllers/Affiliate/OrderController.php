<?php

namespace App\Http\Controllers\Affiliate;

use App\Domain\Orders\OrderService;
use App\Domain\Orders\QuoteCalculator;
use App\Enums\AttributionSource;
use App\Enums\OrderStatus;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Direct affiliate orders. The affiliate, prices, totals and commission are always
 * determined server-side; any such values in the request are ignored.
 */
class OrderController extends Controller
{
    private function me(Request $request): Affiliate
    {
        return $request->user()->affiliate;
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $orders = $this->me($request)->orders()
            ->when(OrderStatus::tryFrom((string) $status), fn ($q, $s) => $q->where('status', $s->value))
            ->latest()->paginate(20)->withQueryString();

        return view('affiliate.orders.index', ['orders' => $orders, 'status' => $status, 'seo' => Seo::make('My orders', null, ['noindex' => true])]);
    }

    public function create(Request $request): View
    {
        return view('affiliate.orders.form', $this->formData(null) + ['seo' => Seo::make('New order', null, ['noindex' => true])]);
    }

    public function edit(Request $request, Order $order): View
    {
        Gate::authorize('affiliateUpdate', $order);

        return view('affiliate.orders.form', $this->formData($order->load('items')) + ['seo' => Seo::make('Edit order', null, ['noindex' => true])]);
    }

    /** Server-calculated preview for step 3 (no overrides allowed for affiliates). */
    public function quote(Request $request, QuoteCalculator $calculator): JsonResponse
    {
        $data = $request->validate($this->lineRules() + ['delivery_required' => ['boolean']]);
        $quote = $calculator->calculate($data['lines'], ['with_commission' => true, 'delivery_required' => $request->boolean('delivery_required', true)]);
        $warnings = collect($quote['items'])->filter(fn ($i) => $i['available'] < $i['quantity'])
            ->map(fn ($i) => "{$i['product_name']} ({$i['variant_name']}): only {$i['available']} in stock — the team will confirm availability.")->values();

        return response()->json([
            'items' => collect($quote['items'])->map(fn ($i) => [
                'name' => $i['product_name'], 'variant' => $i['variant_name'], 'sku' => $i['sku'], 'quantity' => $i['quantity'],
                'unit_price' => $i['unit_price_paise'], 'line_total' => $i['line_total_paise'],
                'commission_rate_bp' => $i['commission_rate_bp'], 'commission' => $i['commission_paise'],
            ]),
            'totals' => $quote['totals'],
            'warnings' => $warnings,
        ]);
    }

    public function store(Request $request, OrderService $orders): RedirectResponse
    {
        $data = $this->validated($request);
        $me = $this->me($request);
        $order = $orders->saveQuote(null, $data + ['channel' => SalesChannel::Affiliate->value], $request->user(), [
            'affiliate' => $me,
            'attribution_source' => AttributionSource::DirectAffiliate,
            'referral_code' => $me->code,
            'status' => OrderStatus::Submitted,
            'idempotency_key' => $data['idempotency_key'],
        ]);

        return redirect()->route('affiliate.orders.show', $order)->with('success', "Order {$order->order_number} submitted. The team will confirm it shortly.");
    }

    public function update(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        Gate::authorize('affiliateUpdate', $order);
        $data = $this->validated($request);
        $orders->saveQuote($order, $data + ['channel' => SalesChannel::Affiliate->value], $request->user(), ['status' => OrderStatus::Submitted]);

        return redirect()->route('affiliate.orders.show', $order)->with('success', 'Order updated.');
    }

    public function show(Request $request, Order $order): View
    {
        Gate::authorize('affiliateView', $order);
        $order->load(['items.units', 'revisions', 'changeRequests', 'commissionEntries', 'shipments']);

        return view('affiliate.orders.show', ['order' => $order, 'canEdit' => Gate::allows('affiliateUpdate', $order), 'seo' => Seo::make('Order '.$order->order_number, null, ['noindex' => true])]);
    }

    public function changeRequest(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('affiliateView', $order);
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:1000']]);
        OrderChangeRequest::create(['order_id' => $order->id, 'affiliate_id' => $this->me($request)->id, 'message' => $data['message']]);

        return back()->with('success', 'Change request sent to the team for review.');
    }

    public function acknowledgeRevision(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('affiliateView', $order);
        $order->revisions()->whereNull('acknowledged_at')->update(['acknowledged_at' => now()]);

        return back()->with('success', 'Revision acknowledged.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate($this->lineRules() + [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'delivery_required' => ['required', 'boolean'],
            'address_line1' => ['required_if:delivery_required,1', 'nullable', 'string', 'max:190'],
            'address_line2' => ['nullable', 'string', 'max:190'],
            'landmark' => ['nullable', 'string', 'max:190'],
            'city' => ['required_if:delivery_required,1', 'nullable', 'string', 'max:100'],
            'state' => ['required_if:delivery_required,1', 'nullable', 'string', 'max:100'],
            'postal_code' => ['required_if:delivery_required,1', 'nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ], ['postal_code.regex' => 'Enter a valid 6-digit PIN code.']);

        // Whitelist: affiliate id, prices, discounts and shipping are never accepted from the client.
        return collect($data)->only([
            'lines', 'customer_name', 'customer_phone', 'customer_email', 'delivery_required', 'address_line1', 'address_line2',
            'landmark', 'city', 'state', 'postal_code', 'customer_notes', 'idempotency_key',
        ])->map(fn ($v, $k) => $k === 'lines' ? array_map(fn ($l) => ['variant_id' => (int) $l['variant_id'], 'quantity' => (int) $l['quantity']], $v) : $v)->all();
    }

    private function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.variant_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /** @return array<string, mixed> */
    private function formData(?Order $order): array
    {
        $products = Product::published()->with(['mainImage', 'activeVariants'])->orderBy('name')->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'image' => $p->mainImage?->url(320),
            'variants' => $p->activeVariants->map(fn ($v) => ['id' => $v->id, 'label' => $v->displayName(), 'sku' => $v->sku, 'price' => $v->effectivePrice(), 'available' => $v->available()])->values(),
        ])->values();

        return [
            'order' => $order,
            'products' => $products,
            'initial' => [
                'lines' => $order ? $order->items->map(fn ($i) => ['variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->values() : [],
                'customer' => $order ? $order->only(['customer_name', 'customer_phone', 'customer_email', 'address_line1', 'address_line2', 'landmark', 'city', 'state', 'postal_code', 'customer_notes']) + ['delivery_required' => $order->delivery_required] : ['delivery_required' => true],
            ],
            'idempotencyKey' => (string) Str::uuid(),
        ];
    }
}
