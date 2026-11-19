@extends('layouts.affiliate')

@section('content')
<h1 class="display text-4xl">Profile and payout details</h1>
<form method="post" action="{{ route('affiliate.profile.update') }}" class="card mt-6 max-w-2xl space-y-5 p-5" novalidate x-data="{ method: @js(old('payout_method', $affiliate->payout_method?->value)) }">
    @csrf @method('put')
    <p class="text-sm">Email: {{ $affiliate->user->email }} · Code: <strong>{{ $affiliate->code }}</strong> · Status: {{ $affiliate->status->label() }}</p>
    <div><label for="name" class="field-label">Name</label><input id="name" name="name" value="{{ old('name', $affiliate->user->name) }}" class="field">@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label for="city" class="field-label">City</label><input id="city" name="city" value="{{ old('city', $affiliate->city) }}" class="field"></div>
        <div><label for="social_handle" class="field-label">Social profile</label><input id="social_handle" name="social_handle" value="{{ old('social_handle', $affiliate->social_handle) }}" class="field"></div>
    </div>
    <fieldset class="space-y-4 rounded-xl bg-pearl p-4">
        <legend class="text-sm font-semibold">Payout details (private)</legend>
        <p class="text-xs text-muted">Current: {{ $affiliate->maskedPayoutDestination() ?? 'not set' }}. Saved numbers are never shown in full; enter new values only to change them.</p>
        <div>
            <label for="payout_method" class="field-label">Method</label>
            <select id="payout_method" name="payout_method" x-model="method" class="field"><option value="">Not set</option>@foreach (\App\Enums\PayoutMethod::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </div>
        <div x-show="method === 'upi'"><label for="payout_upi_id" class="field-label">UPI ID</label><input id="payout_upi_id" name="payout_upi_id" autocomplete="off" class="field" placeholder="name@bank">@error('payout_upi_id')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div x-show="method === 'bank_transfer'" class="space-y-4">
            <div><label for="payout_account_name" class="field-label">Account holder name</label><input id="payout_account_name" name="payout_account_name" autocomplete="off" class="field">@error('payout_account_name')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="payout_account_number" class="field-label">Account number</label><input id="payout_account_number" name="payout_account_number" inputmode="numeric" autocomplete="off" class="field">@error('payout_account_number')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label for="payout_ifsc" class="field-label">IFSC</label><input id="payout_ifsc" name="payout_ifsc" autocomplete="off" class="field uppercase" maxlength="11">@error('payout_ifsc')<p class="field-error">{{ $message }}</p>@enderror</div>
        </div>
    </fieldset>
    <button class="btn-primary">Save</button>
</form>
@endsection
