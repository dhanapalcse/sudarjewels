@extends('layouts.app')

@section('content')
<div class="container-page max-w-xl pt-8">
    <h1 class="display text-4xl">Check your phone</h1>
    <p class="mt-3 text-muted" role="status">If the details you entered match an order, we have sent a verification code to <strong class="text-charcoal">{{ $maskedPhone }}</strong>.</p>
    @include('partials.otp-form', ['action' => route('warranty.verify.submit')])
    <div class="mt-5 flex flex-wrap gap-4 text-sm">
        <form method="post" action="{{ route('warranty.resend') }}">@csrf<button class="text-teal underline">Send a new code</button></form>
        <a href="{{ route('warranty.start') }}" class="text-teal underline">Change details</a>
    </div>
</div>
@endsection
