@extends('layouts.account')

@section('account')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="display text-4xl">My claims</h1>
    <a href="{{ route('account.claims.create') }}" class="btn-primary">Submit a claim</a>
</div>
@if ($claims->isEmpty())
    <p class="mt-6 rounded-xl bg-pearl p-6 text-sm text-muted">You haven't submitted any claims.</p>
@else
    <ul class="mt-6 divide-y divide-line rounded-2xl border border-line">
        @foreach ($claims as $claim)
            <li><a href="{{ route('account.claims.show', $claim) }}" class="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-pearl">
                <span><span class="font-medium">{{ $claim->claim_number }}</span><span class="block text-sm text-muted">{{ $claim->unit->orderItem->product_name }} · {{ $claim->created_at->timezone(config('sudar.business.timezone'))->format('j M Y') }}</span></span>
                @include('partials.status-badge', ['status' => $claim->status])
            </a></li>
        @endforeach
    </ul>
@endif
@endsection
