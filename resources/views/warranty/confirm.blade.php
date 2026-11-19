@extends('layouts.app')

@section('content')
<div class="container-page max-w-2xl pt-8">
    <h1 class="display text-4xl">Confirm your purchase</h1>
    <p class="mt-2 text-muted">Order <strong class="text-charcoal">{{ $order->order_number }}</strong> · {{ $order->confirmed_at?->timezone(config('sudar.business.timezone'))->format('j M Y') }}</p>

    @if ($existing)
        <p class="mt-6 rounded-xl bg-mint px-4 py-3 text-sm" role="status">This order is already registered. Continuing will show your card and add any newly delivered pieces.</p>
    @endif

    <div class="card mt-6 divide-y divide-line">
        @foreach ($order->items as $item)
            <div class="p-4">
                <p class="font-medium">{{ $item->product_name }}</p>
                <p class="text-sm text-muted">{{ $item->variant_name }} · SKU {{ $item->sku }} · Qty {{ $item->quantity }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($item->units as $unit)
                        <li class="flex items-center justify-between gap-3">
                            <span>Piece {{ $unit->unit_number }}</span>
                            <span class="text-muted">
                                @if (! $unit->warranty_eligible) Not covered by warranty
                                @elseif ($unit->warranty_status->value === 'registered') Registered · until {{ $unit->warranty_ends_at?->timezone(config('sudar.business.timezone'))->format('j M Y') }}
                                @elseif ($unit->warranty_status->value === 'void') Not eligible (returned or refunded)
                                @elseif ($eligible->contains('id', $unit->id)) Ready to register · until {{ $unit->warranty_ends_at?->timezone(config('sudar.business.timezone'))->format('j M Y') }}
                                @else Will be added automatically after delivery
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <form method="post" action="{{ route('warranty.register') }}" class="mt-6 space-y-4">
        @csrf
        <p class="text-sm">Registration name: <strong>{{ $flow['name'] }}</strong></p>
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="confirm" value="1" class="mt-0.5 size-5 rounded border-line text-teal focus:ring-teal" required>
            <span>I confirm these are the pieces I purchased and agree to the <a href="{{ route('pages.warranty-coverage') }}" class="text-teal underline" target="_blank">warranty terms</a>.</span>
        </label>
        @error('confirm')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn-primary w-full sm:w-auto">{{ $existing ? 'View my warranty card' : 'Register warranty' }}</button>
    </form>
</div>
@endsection
