@extends('layouts.account')

@php($tz = config('sudar.business.timezone'))

@section('account')
<a href="{{ route('account.claims') }}" class="text-sm text-teal underline">← All claims</a>
<div class="mt-3 flex flex-wrap items-center gap-3">
    <h1 class="display text-4xl">{{ $claim->claim_number }}</h1>
    @include('partials.status-badge', ['status' => $claim->status])
</div>
<p class="mt-1 text-sm text-muted">{{ $claim->unit->orderItem->product_name }} · {{ $claim->unit->order->order_number }} · {{ $claim->unit->code }}</p>

@if ($claim->decision_reason)
    <div class="mt-6 rounded-xl bg-pearl p-4 text-sm"><p class="font-medium">Decision</p><p class="mt-1">{{ $claim->decision_reason }}</p></div>
@endif
@if ($claim->replacementUnit)
    <p class="mt-3 rounded-xl bg-mint p-4 text-sm">A replacement ({{ $claim->replacementUnit->code }}) has been issued with coverage until {{ $claim->replacementUnit->warranty_ends_at?->timezone($tz)->format('j M Y') }}.</p>
@endif

<ol class="mt-8 space-y-4">
    @foreach ($claim->updates as $update)
        <li class="card p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="font-medium">{{ $update->author_type === 'customer' ? 'You' : 'SUDAR JEWELS' }}</span>
                <span class="text-muted">{{ $update->created_at->timezone($tz)->format('j M Y, g:i a') }}</span>
            </div>
            @if ($update->status_to && $update->status_from)<p class="mt-1 text-xs text-muted">Status: {{ $update->status_to->label() }}</p>@endif
            @if ($update->message)<p class="mt-2 whitespace-pre-line text-sm">{{ $update->message }}</p>@endif
            @if ($update->attachments->isNotEmpty())
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($update->attachments as $attachment)
                        <li><a href="{{ route('account.claims.attachment', [$claim, $attachment]) }}" target="_blank" class="block size-20 overflow-hidden rounded-lg border border-line"><img src="{{ route('account.claims.attachment', [$claim, $attachment]) }}" alt="Attachment {{ $loop->iteration }}" class="h-full w-full object-cover" loading="lazy"></a></li>
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ol>

@if ($claim->status->value === 'more_info_required')
    <form method="post" action="{{ route('account.claims.reply', $claim) }}" enctype="multipart/form-data" class="card mt-8 space-y-4 p-5" novalidate>
        @csrf
        <h2 class="font-semibold">Send more information</h2>
        <div>
            <label for="message" class="field-label">Message</label>
            <textarea id="message" name="message" rows="4" required class="field">{{ old('message') }}</textarea>
            @error('message')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        @include('account.partials.photo-input')
        <button class="btn-primary">Send</button>
    </form>
@endif
@endsection
