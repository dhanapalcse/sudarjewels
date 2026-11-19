@extends('layouts.account')

@section('account')
<h1 class="display text-4xl">Submit a warranty claim</h1>
@if ($units->isEmpty())
    <p class="mt-6 rounded-xl bg-pearl p-6 text-sm text-muted">You have no registered pieces with active coverage and no open claim. <a href="{{ route('warranty.start') }}" class="text-teal underline">Register a warranty</a>.</p>
@else
    <form method="post" action="{{ route('account.claims.store') }}" enctype="multipart/form-data" class="card mt-6 max-w-2xl space-y-5 p-5" novalidate x-data="{ sending: false }" @submit="sending = true">
        @csrf
        <fieldset>
            <legend class="field-label">Which piece?</legend>
            <div class="space-y-2">
                @foreach ($units as $unit)
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line p-3 has-[:checked]:border-teal has-[:checked]:bg-mint">
                        <input type="radio" name="purchased_unit_id" value="{{ $unit->id }}" class="mt-1 text-teal focus:ring-teal" @checked(old('purchased_unit_id', $selected) == $unit->id) required>
                        <span class="text-sm"><span class="font-medium">{{ $unit->orderItem->product_name }}</span> — {{ $unit->orderItem->variant_name }}<span class="block text-muted">{{ $unit->order->order_number }} · {{ $unit->code }} · covered until {{ $unit->warranty_ends_at->timezone(config('sudar.business.timezone'))->format('j M Y') }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('purchased_unit_id')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        <div>
            <label for="issue_description" class="field-label">Describe the issue</label>
            <textarea id="issue_description" name="issue_description" rows="5" required minlength="20" maxlength="2000" class="field">{{ old('issue_description') }}</textarea>
            @error('issue_description')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        @include('account.partials.photo-input')
        <button class="btn-primary" :disabled="sending"><span x-text="sending ? 'Submitting…' : 'Submit claim'">Submit claim</span></button>
    </form>
@endif
@endsection
