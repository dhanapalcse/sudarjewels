@extends('layouts.affiliate')

@section('content')
<h1 class="display text-4xl">Payout history</h1>
@if ($payouts->isEmpty())
    <p class="mt-6 rounded-xl bg-white p-6 text-sm text-muted">No payouts yet.</p>
@else
    <ul class="mt-6 divide-y divide-line rounded-2xl border border-line bg-white">
        @foreach ($payouts as $p)
            <li class="flex flex-wrap items-center justify-between gap-2 p-4 text-sm">
                <span><span class="font-medium">{{ \App\Support\Money::format($p->amount_paise) }}</span><span class="block text-muted">{{ $p->method?->label() }} {{ $p->reference ? '· ref '.$p->reference : '' }} · {{ ($p->paid_on ?? $p->created_at)->format('j M Y') }}</span></span>
                @include('partials.status-badge', ['status' => $p->status])
            </li>
        @endforeach
    </ul>
    <div class="mt-6">{{ $payouts->links() }}</div>
@endif
@endsection
