@extends('layouts.app')

@php
    $wa = $settings->whatsappNumber();
    $phone = $settings->get('business.phone');
    $email = $settings->get('business.email');
    $address = $settings->get('business.address');
    $hours = $settings->get('business.hours');
@endphp

@section('content')
<div class="container-page max-w-3xl pt-8">
    <h1 class="display text-4xl sm:text-5xl">Contact us</h1>
    <p class="mt-3 text-muted">We are happy to help you choose a piece, check an order, or answer warranty questions.</p>
    <ul class="mt-8 grid gap-4 sm:grid-cols-2">
        @if ($wa)
            <li class="card p-5"><x-sj-icon name="whatsapp" class="size-6 text-teal" /><h2 class="mt-3 font-semibold">WhatsApp</h2><a class="mt-1 block text-teal underline" href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener">Chat with us</a></li>
        @endif
        @if ($phone)
            <li class="card p-5"><x-sj-icon name="phone" class="size-6 text-teal" /><h2 class="mt-3 font-semibold">Phone</h2><a class="mt-1 block text-teal underline" href="tel:{{ $phone }}">{{ $phone }}</a></li>
        @endif
        @if ($email)
            <li class="card p-5"><x-sj-icon name="mail" class="size-6 text-teal" /><h2 class="mt-3 font-semibold">Email</h2><a class="mt-1 block text-teal underline" href="mailto:{{ $email }}">{{ $email }}</a></li>
        @endif
        @if ($address)
            <li class="card p-5"><x-sj-icon name="pin" class="size-6 text-teal" /><h2 class="mt-3 font-semibold">Address</h2><p class="mt-1 whitespace-pre-line text-sm">{{ $address }}</p></li>
        @endif
    </ul>
    @if (! $wa && ! $phone && ! $email)
        <p class="mt-6 rounded-xl border border-gold bg-pearl px-4 py-3 text-sm" role="note">Contact details have not been configured yet (Admin → Business settings).</p>
    @endif
    @if ($hours)<p class="mt-6 text-sm text-muted">Hours: {{ $hours }}</p>@endif
    @if ($page)
        <div class="prose-sj mt-8">{!! \Illuminate\Support\Str::markdown((string) $page->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
    @endif
</div>
@endsection
