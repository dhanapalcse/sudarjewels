@extends('layouts.app')

@section('content')
<div class="container-page max-w-xl pt-8">
    <x-sj-icon name="shield" class="size-10 text-teal" />
    <h1 class="display mt-3 text-4xl sm:text-5xl">Register your warranty</h1>
    <p class="mt-3 text-muted">Enter the order number from your invoice or message, with the name and mobile number used for the purchase. We'll send a one-time code to that number.</p>

    <form method="post" action="{{ route('warranty.lookup') }}" class="card mt-8 space-y-5 p-5 sm:p-6" novalidate>
        @csrf
        <div>
            <label for="order_number" class="field-label">Order number</label>
            <input id="order_number" name="order_number" value="{{ old('order_number') }}" required autocomplete="off" autocapitalize="characters" placeholder="e.g. SJ000123" class="field uppercase" aria-describedby="order-help @error('order_number') order-error @enderror">
            <p id="order-help" class="field-help">Starts with SJ.</p>
            @error('order_number')<p id="order-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="field-label">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" class="field" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p id="name-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone" class="field-label">Mobile number</label>
            <div class="flex">
                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-line bg-pearl px-3 text-sm text-muted">+91</span>
                <input id="phone" name="phone" value="{{ old('phone') }}" required inputmode="numeric" autocomplete="tel-national" maxlength="14" class="field rounded-l-none" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
            </div>
            @error('phone')<p id="phone-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">Send verification code</button>
        <p class="text-xs text-muted">Your details are used only to verify your purchase. See our <a href="{{ route('pages.privacy') }}" class="text-teal underline">privacy policy</a>.</p>
    </form>
    <p class="mt-6 text-sm"><a href="{{ route('pages.warranty-coverage') }}" class="text-teal underline">What does the warranty cover?</a></p>
</div>
@endsection
