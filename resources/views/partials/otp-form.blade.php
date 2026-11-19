<form method="post" action="{{ $action }}" class="card mt-8 space-y-5 p-5 sm:p-6" novalidate>
    @csrf
    @include('partials.test-driver-banner')
    <div>
        <label for="code" class="field-label">{{ config('sudar.otp.length') }}-digit code</label>
        <input id="code" name="code" required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="{{ config('sudar.otp.length') }}"
               class="field text-center font-mono text-2xl tracking-[0.5em]" autofocus @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
        @error('code')<p id="code-error" class="field-error" role="alert">{{ $message }}</p>@enderror
        <p class="field-help">The code expires in {{ config('sudar.otp.ttl_minutes') }} minutes.</p>
    </div>
    <button class="btn-primary w-full">Verify</button>
</form>
