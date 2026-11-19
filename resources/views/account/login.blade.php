@extends('layouts.app')

@section('content')
<div class="container-page max-w-md pt-10">
    <h1 class="display text-4xl">Sign in</h1>
    <p class="mt-2 text-muted">Use the mobile number from your order. We'll send you a one-time code.</p>
    <form method="post" action="{{ route('customer.login.send') }}" class="card mt-8 space-y-5 p-5" novalidate>
        @csrf
        <div>
            <label for="phone" class="field-label">Mobile number</label>
            <div class="flex">
                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-line bg-pearl px-3 text-sm text-muted">+91</span>
                <input id="phone" name="phone" value="{{ old('phone') }}" required inputmode="numeric" autocomplete="tel-national" maxlength="14" class="field rounded-l-none" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
            </div>
            @error('phone')<p id="phone-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">Send code</button>
    </form>
    <p class="mt-6 text-sm text-muted">New here? Your account is created with your first order. Affiliates sign in <a class="text-teal underline" href="{{ route('affiliate.login') }}">here</a>.</p>
</div>
@endsection
