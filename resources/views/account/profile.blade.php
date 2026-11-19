@extends('layouts.account')

@section('account')
<h1 class="display text-4xl">Profile</h1>
<form method="post" action="{{ route('account.profile.update') }}" class="card mt-6 max-w-xl space-y-5 p-5" novalidate>
    @csrf @method('put')
    <div>
        <label for="name" class="field-label">Name</label>
        <input id="name" name="name" value="{{ old('name', $customer->name) }}" required class="field" autocomplete="name">
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div>
        <p class="field-label">Mobile number</p>
        <p class="text-sm">{{ \App\Support\Phone::display($customer->phone) }} <span class="text-muted">— contact us to change it.</span></p>
    </div>
    <div>
        <label for="email" class="field-label">Email <span class="font-normal text-muted">(optional)</span></label>
        <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" class="field" autocomplete="email">
        @error('email')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <fieldset class="rounded-xl bg-pearl p-4">
        <legend class="sr-only">Communication preferences</legend>
        <label class="flex items-start gap-3 text-sm">
            <input type="hidden" name="marketing_opt_in" value="0">
            <input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in', $customer->marketing_opt_in)) class="mt-0.5 size-5 rounded border-line text-teal focus:ring-teal">
            <span><span class="font-medium">Send me new arrivals and offers</span><span class="block text-muted">Optional. Order, delivery and warranty messages are always sent because they're needed for your purchase.</span></span>
        </label>
        @if ($customer->marketing_consent_at)<p class="mt-2 text-xs text-muted">Preference last changed {{ $customer->marketing_consent_at->timezone(config('sudar.business.timezone'))->format('j M Y') }}.</p>@endif
    </fieldset>
    <button class="btn-primary">Save</button>
</form>
@endsection
