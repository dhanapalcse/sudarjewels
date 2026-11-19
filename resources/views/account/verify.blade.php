@extends('layouts.app')

@section('content')
<div class="container-page max-w-md pt-10">
    <h1 class="display text-4xl">Enter your code</h1>
    <p class="mt-2 text-muted" role="status">If {{ $maskedPhone }} is linked to an order with us, a code is on its way.</p>
    @include('partials.otp-form', ['action' => route('customer.login.verify.submit')])
    <p class="mt-5 text-sm"><a href="{{ route('customer.login') }}" class="text-teal underline">Use a different number or resend</a></p>
</div>
@endsection
