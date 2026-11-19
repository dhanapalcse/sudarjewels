@extends('layouts.affiliate')

@section('content')
<div x-data="affiliateOrder(@js($products), @js($initial), @js(route('affiliate.orders.quote')), @js(csrf_token()))" class="mx-auto max-w-3xl">
    <h1 class="display text-4xl">{{ $order ? 'Edit order '.$order->order_number : 'New order' }}</h1>
    <ol class="mt-4 grid grid-cols-3 gap-2 text-xs sm:text-sm" aria-label="Steps">
        <template x-for="(label, i) in ['Products', 'Customer', 'Review']" :key="i">
            <li class="rounded-full px-3 py-2 text-center" :class="step === i + 1 ? 'bg-teal text-white' : (step > i + 1 ? 'bg-mint text-teal' : 'bg-white text-muted')" :aria-current="step === i + 1 ? 'step' : null"><span x-text="`${i + 1}. ${label}`"></span></li>
        </template>
    </ol>

    <form method="post" action="{{ $order ? route('affiliate.orders.update', $order) : route('affiliate.orders.store') }}" class="mt-6" @submit="submitting = true" novalidate>
        @csrf
        @if ($order) @method('put') @endif
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <template x-for="(line, i) in lines" :key="line.variant_id">
            <span><input type="hidden" :name="`lines[${i}][variant_id]`" :value="line.variant_id"><input type="hidden" :name="`lines[${i}][quantity]`" :value="line.quantity"></span>
        </template>

        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-danger" role="alert"><p class="font-medium">Please fix the following:</p><ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        {{-- Step 1: products --}}
        <section x-show="step === 1" aria-labelledby="s1">
            <h2 id="s1" class="sr-only">Select products</h2>
            <label for="psearch" class="sr-only">Search products</label>
            <input id="psearch" type="search" x-model="search" placeholder="Search by name or SKU" class="field">
            <ul class="mt-3 max-h-[50vh] space-y-2 overflow-y-auto">
                <template x-for="p in filtered" :key="p.id">
                    <li class="card flex gap-3 p-3">
                        <img :src="p.image" alt="" class="size-16 shrink-0 rounded-lg bg-pearl object-cover" loading="lazy" width="64" height="64">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium" x-text="p.name"></p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="v in p.variants" :key="v.id">
                                    <button type="button" class="chip text-xs" :class="qty(v.id) && 'chip-active'" @click="add(p, v)" :disabled="v.available < 1">
                                        <span x-text="`${v.label} · ${money(v.price)}${v.available < 1 ? ' · sold out' : ''}`"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
            <h3 class="mt-6 font-semibold">Selected (<span x-text="lines.length"></span>)</h3>
            <p x-show="!lines.length" class="mt-2 text-sm text-muted">Tap an option above to add it.</p>
            <ul class="mt-2 divide-y divide-line rounded-2xl border border-line bg-white">
                <template x-for="line in lines" :key="line.variant_id">
                    <li class="flex items-center justify-between gap-3 p-3 text-sm">
                        <span class="min-w-0"><span class="font-medium" x-text="line.name"></span><span class="block text-muted" x-text="`${line.label} · ${money(line.price)}`"></span></span>
                        <span class="inline-flex items-center rounded-full border border-line">
                            <button type="button" class="btn-ghost min-h-10 px-3" @click="change(line, -1)"><x-sj-icon name="minus" class="size-4" /><span class="sr-only">Decrease</span></button>
                            <span class="w-6 text-center" x-text="line.quantity" aria-live="polite"></span>
                            <button type="button" class="btn-ghost min-h-10 px-3" @click="change(line, 1)" :disabled="line.quantity >= 20"><x-sj-icon name="plus" class="size-4" /><span class="sr-only">Increase</span></button>
                        </span>
                    </li>
                </template>
            </ul>
            <button type="button" class="btn-primary mt-6 w-full" :disabled="!lines.length" @click="step = 2">Continue to customer details</button>
        </section>

        {{-- Step 2: customer --}}
        <section x-show="step === 2" x-cloak aria-labelledby="s2" class="card space-y-4 p-5">
            <h2 id="s2" class="text-lg font-semibold">Customer and delivery</h2>
            <div><label class="field-label" for="customer_name">Customer name</label><input id="customer_name" name="customer_name" x-model="c.customer_name" required class="field" autocomplete="off"></div>
            <div><label class="field-label" for="customer_phone">Mobile number</label><input id="customer_phone" name="customer_phone" x-model="c.customer_phone" required inputmode="numeric" class="field" autocomplete="off"></div>
            <div><label class="field-label" for="customer_email">Email (optional)</label><input id="customer_email" name="customer_email" type="email" x-model="c.customer_email" class="field" autocomplete="off"></div>
            <input type="hidden" name="delivery_required" :value="c.delivery_required ? 1 : 0">
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" x-model="c.delivery_required" class="size-5 rounded border-line text-teal"> Deliver to the customer</label>
            <div x-show="c.delivery_required" class="space-y-4">
                <div><label class="field-label" for="address_line1">Address line 1</label><input id="address_line1" name="address_line1" x-model="c.address_line1" class="field" autocomplete="off"></div>
                <div><label class="field-label" for="address_line2">Address line 2 (optional)</label><input id="address_line2" name="address_line2" x-model="c.address_line2" class="field" autocomplete="off"></div>
                <div><label class="field-label" for="landmark">Landmark (optional)</label><input id="landmark" name="landmark" x-model="c.landmark" class="field" autocomplete="off"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="field-label" for="city">City</label><input id="city" name="city" x-model="c.city" class="field" autocomplete="off"></div>
                    <div><label class="field-label" for="postal_code">PIN code</label><input id="postal_code" name="postal_code" x-model="c.postal_code" inputmode="numeric" maxlength="6" class="field" autocomplete="off"></div>
                </div>
                <div><label class="field-label" for="state">State</label><input id="state" name="state" x-model="c.state" class="field" autocomplete="off"></div>
            </div>
            <div><label class="field-label" for="customer_notes">Notes for the team (optional)</label><textarea id="customer_notes" name="customer_notes" x-model="c.customer_notes" rows="2" maxlength="500" class="field"></textarea></div>
            <p x-show="stepError" x-text="stepError" class="field-error" role="alert"></p>
            <div class="flex gap-3">
                <button type="button" class="btn-secondary flex-1" @click="step = 1">Back</button>
                <button type="button" class="btn-primary flex-1" @click="review()">Review order</button>
            </div>
        </section>

        {{-- Step 3: review (server-calculated) --}}
        <section x-show="step === 3" x-cloak aria-labelledby="s3" class="card p-5" aria-live="polite">
            <h2 id="s3" class="text-lg font-semibold">Review</h2>
            <p x-show="loading" class="mt-3 text-sm text-muted">Calculating…</p>
            <template x-if="quote">
                <div>
                    <ul class="mt-3 divide-y divide-line text-sm">
                        <template x-for="item in quote.items" :key="item.sku">
                            <li class="flex justify-between gap-3 py-2"><span><span x-text="`${item.quantity} × ${item.name}`"></span><span class="block text-muted" x-text="`${item.variant} · commission ${(item.commission_rate_bp / 100).toFixed(2).replace(/\.00$/, '')}%`"></span></span><span x-text="money(item.line_total)"></span></li>
                        </template>
                    </ul>
                    <dl class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
                        <div class="flex justify-between"><dt>Shipping</dt><dd x-text="quote.totals.shipping_paise ? money(quote.totals.shipping_paise) : 'Free'"></dd></div>
                        <div class="flex justify-between text-base font-semibold"><dt>Customer total</dt><dd x-text="money(quote.totals.grand_total_paise)"></dd></div>
                        <div class="flex justify-between text-teal"><dt>Your estimated commission</dt><dd x-text="money(quote.totals.commission_estimate_paise)"></dd></div>
                    </dl>
                    <ul class="mt-3 space-y-1 text-sm text-amber-900"><template x-for="w in quote.warnings"><li x-text="w"></li></template></ul>
                    <p class="mt-3 text-xs text-muted">Prices and stock are re-checked when the team confirms. If anything changes, you'll see a revised quote before confirmation. Commission becomes payable after full payment, delivery and the return window.</p>
                </div>
            </template>
            <p x-show="quoteError" x-text="quoteError" class="field-error mt-3" role="alert"></p>
            <div class="mt-5 flex gap-3">
                <button type="button" class="btn-secondary flex-1" @click="step = 2">Back</button>
                <button type="submit" class="btn-primary flex-1" :disabled="!quote || submitting"><span x-text="submitting ? 'Submitting…' : '{{ $order ? 'Save changes' : 'Submit order' }}'"></span></button>
            </div>
        </section>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('affiliateOrder', (products, initial, quoteUrl, csrf) => ({
        products, step: 1, search: '', submitting: false, loading: false, quote: null, quoteError: '', stepError: '',
        lines: [],
        c: Object.assign({ customer_name: '', customer_phone: '', customer_email: '', address_line1: '', address_line2: '', landmark: '', city: '', state: '', postal_code: '', customer_notes: '' }, initial.customer, @js(old() ? collect(old())->except(['_token', 'lines', '_method', 'idempotency_key'])->all() : [])),
        init() {
            const find = (id) => { for (const p of this.products) { const v = p.variants.find(v => v.id === id); if (v) return [p, v]; } return []; };
            for (const l of initial.lines) { const [p, v] = find(l.variant_id); if (v) this.lines.push({ variant_id: v.id, name: p.name, label: v.label, price: v.price, quantity: l.quantity }); }
            this.c.delivery_required = !!(this.c.delivery_required === true || this.c.delivery_required === '1' || this.c.delivery_required === 1);
        },
        get filtered() { const q = this.search.toLowerCase(); return q ? this.products.filter(p => p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || p.variants.some(v => v.sku.toLowerCase().includes(q))) : this.products; },
        money(p) { return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: p % 100 ? 2 : 0 }).format(p / 100); },
        qty(id) { return this.lines.find(l => l.variant_id === id)?.quantity || 0; },
        add(p, v) { const l = this.lines.find(l => l.variant_id === v.id); if (l) { if (l.quantity < 20) l.quantity++; } else this.lines.push({ variant_id: v.id, name: p.name, label: v.label, price: v.price, quantity: 1 }); },
        change(line, d) { line.quantity += d; if (line.quantity < 1) this.lines = this.lines.filter(l => l !== line); },
        review() {
            this.stepError = '';
            if (!this.c.customer_name.trim()) return this.stepError = 'Enter the customer name.';
            if (!/^(\+?91)?[6-9]\d{9}$/.test(this.c.customer_phone.replace(/[\s-]/g, ''))) return this.stepError = 'Enter a valid 10-digit mobile number.';
            if (this.c.delivery_required && (!this.c.address_line1 || !this.c.city || !this.c.state || !/^[1-9]\d{5}$/.test(this.c.postal_code))) return this.stepError = 'Complete the delivery address with a 6-digit PIN code.';
            this.step = 3; this.fetchQuote();
        },
        async fetchQuote() {
            this.loading = true; this.quote = null; this.quoteError = '';
            try {
                const res = await fetch(quoteUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ lines: this.lines.map(l => ({ variant_id: l.variant_id, quantity: l.quantity })), delivery_required: this.c.delivery_required }) });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Could not calculate the order.');
                this.quote = data;
            } catch (e) { this.quoteError = e.message; } finally { this.loading = false; }
        },
    }));
});
</script>
@if ($errors->any())
    <script>document.addEventListener('alpine:initialized', () => { const el = document.querySelector('[x-data^=affiliateOrder]'); if (el && el._x_dataStack) el._x_dataStack[0].step = 2; });</script>
@endif
@endsection
