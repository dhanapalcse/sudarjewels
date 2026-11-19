@extends('layouts.app')

@section('content')
<div class="container-page max-w-xl pt-10">
    <h1 class="display text-4xl">Become an affiliate</h1>
    <p class="mt-2 text-muted">Share pieces you love and earn commission on confirmed sales. Applications are reviewed by our team.</p>
    <form method="post" action="{{ route('affiliate.apply.submit') }}" class="card mt-8 space-y-5 p-5" novalidate>
        @csrf
        @foreach ([['name', 'Full name', 'text', 'name'], ['email', 'Email', 'email', 'email'], ['phone', 'Mobile number', 'tel', 'tel'], ['city', 'City (optional)', 'text', 'address-level2'], ['social_handle', 'Instagram or social profile (optional)', 'text', 'off']] as [$f, $l, $t, $ac])
            <div>
                <label for="{{ $f }}" class="field-label">{{ $l }}</label>
                <input id="{{ $f }}" name="{{ $f }}" type="{{ $t }}" value="{{ old($f) }}" autocomplete="{{ $ac }}" class="field" @error($f) aria-invalid="true" @enderror>
                @error($f)<p class="field-error">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div>
            <label for="password" class="field-label">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" class="field" aria-describedby="pw-help">
            <p id="pw-help" class="field-help">At least 10 characters with letters and numbers.</p>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="field">
        </div>
        <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="terms" value="1" class="mt-0.5 size-5 rounded border-line text-teal"> <span>I agree to the <a href="{{ route('pages.affiliate-terms') }}" target="_blank" class="text-teal underline">affiliate terms</a>.</span></label>
        @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn-primary w-full">Submit application</button>
    </form>
</div>
@endsection
