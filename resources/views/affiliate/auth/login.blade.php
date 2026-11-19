@extends('layouts.app')

@section('content')
<div class="container-page max-w-md pt-10">
    <h1 class="display text-4xl">Affiliate login</h1>
    <form method="post" action="{{ route('affiliate.login.submit') }}" class="card mt-8 space-y-5 p-5" novalidate>
        @csrf
        <div>
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="field">
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="field-label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="field">
        </div>
        <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="remember" value="1" class="size-5 rounded border-line text-teal"> Keep me signed in</label>
        <button class="btn-primary w-full">Sign in</button>
    </form>
    <p class="mt-6 text-sm text-muted">Not an affiliate yet? <a href="{{ route('affiliate.apply') }}" class="text-teal underline">Apply here</a>. Staff use the <a href="/admin" class="text-teal underline">admin dashboard</a>.</p>
</div>
@endsection
