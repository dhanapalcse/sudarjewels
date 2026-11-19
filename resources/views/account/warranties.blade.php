@extends('layouts.account')

@php($tz = config('sudar.business.timezone'))

@section('account')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="display text-4xl">My warranties</h1>
    <a href="{{ route('warranty.start') }}" class="btn-secondary">Register another order</a>
</div>
@if ($units->isEmpty())
    <p class="mt-6 rounded-xl bg-pearl p-6 text-sm text-muted">No warranty-eligible pieces yet.</p>
@else
    <ul class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach ($units as $unit)
            <li class="card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ $unit->orderItem->product_name }}</p>
                        <p class="text-sm text-muted">{{ $unit->order->order_number }} · {{ $unit->code }}</p>
                    </div>
                    @if ($unit->isCovered())<span class="badge bg-mint text-teal-900">Active</span>
                    @elseif ($unit->warranty_status->value === 'unregistered')<span class="badge bg-amber-50 text-amber-900">Not registered</span>
                    @elseif ($unit->warranty_status->value === 'void')<span class="badge bg-red-50 text-danger">Void</span>
                    @else<span class="badge bg-pearl text-charcoal ring-1 ring-line">Expired</span>@endif
                </div>
                @if ($unit->warranty_ends_at)<p class="mt-2 text-sm">Until {{ $unit->warranty_ends_at->timezone($tz)->format('j M Y') }}</p>@endif
                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                    @if ($unit->registration)<a href="{{ route('warranty.card', $unit->registration) }}" class="text-teal underline">Card</a>@endif
                    @if ($unit->isCovered() && ! $unit->claims->contains(fn ($c) => $c->status->isOpen()))<a href="{{ route('account.claims.create', ['unit' => $unit->id]) }}" class="text-teal underline">Submit claim</a>@endif
                    @if ($unit->warranty_status->value === 'unregistered')<a href="{{ route('warranty.start') }}" class="text-teal underline">Register</a>@endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
