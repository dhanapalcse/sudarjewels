@extends('layouts.app')

@php($tz = config('sudar.business.timezone'))

@section('content')
<div class="container-page max-w-2xl pt-8">
    <article class="overflow-hidden rounded-2xl border border-line" aria-labelledby="card-title">
        <div class="bg-teal px-6 py-6 text-white">
            <p class="font-display text-lg tracking-[0.2em]">SUDAR JEWELS</p>
            <h1 id="card-title" class="display mt-2 text-4xl text-white">Warranty card</h1>
        </div>
        <dl class="grid grid-cols-2 gap-4 border-b border-line p-6 text-sm">
            <div><dt class="text-muted">Registered to</dt><dd class="font-medium">{{ $registration->registered_name }}</dd></div>
            <div><dt class="text-muted">Order</dt><dd class="font-medium">{{ $registration->order->order_number }}</dd></div>
            <div><dt class="text-muted">Registered on</dt><dd>{{ $registration->registered_at->timezone($tz)->format('j M Y, g:i a') }}</dd></div>
            <div><dt class="text-muted">Card ID</dt><dd class="font-mono text-xs">{{ strtoupper(substr($registration->uuid, 0, 8)) }}</dd></div>
        </dl>
        <ul class="divide-y divide-line">
            @foreach ($registration->units as $unit)
                <li class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-medium">{{ $unit->orderItem->product_name }}</p>
                            <p class="text-sm text-muted">{{ $unit->orderItem->variant_name }} · {{ $unit->code }}@if ($unit->replacement_of_unit_id) · Replacement @endif</p>
                        </div>
                        @if ($unit->isCovered())
                            <span class="badge bg-mint text-teal-900">Active</span>
                        @elseif ($unit->warranty_status->value === 'void')
                            <span class="badge bg-red-50 text-danger">Void</span>
                        @else
                            <span class="badge bg-pearl text-charcoal ring-1 ring-line">Expired</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm">Covered {{ $unit->warranty_starts_at?->timezone($tz)->format('j M Y') }} – {{ $unit->warranty_ends_at?->timezone($tz)->format('j M Y') }} (11:59 pm IST)</p>
                    @if ($unit->warranty_terms)<p class="mt-1 text-xs text-muted">{{ $unit->warranty_terms }}</p>@endif
                </li>
            @endforeach
            @if ($registration->units->isEmpty())
                <li class="p-6 text-sm text-muted">No pieces have been delivered yet. They will be added automatically after delivery.</li>
            @endif
        </ul>
        @if ($pending > 0)
            <p class="border-t border-line bg-pearl px-6 py-3 text-sm">{{ $pending }} more {{ \Illuminate\Support\Str::plural('piece', $pending) }} will be added automatically once delivered.</p>
        @endif
    </article>
    <div class="no-print mt-6 flex flex-wrap gap-3">
        <button type="button" onclick="window.print()" class="btn-secondary"><x-sj-icon name="print" class="size-4" />Print or save</button>
        @auth('customer')
            <a href="{{ route('account.claims.create') }}" class="btn-primary">Submit a claim</a>
            <a href="{{ route('account.warranties') }}" class="btn-ghost">My warranties</a>
        @endauth
    </div>
</div>
@endsection
