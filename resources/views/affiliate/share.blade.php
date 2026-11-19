@extends('layouts.affiliate')

@section('content')
<h1 class="display text-4xl">Share products</h1>
<p class="mt-2 text-sm text-muted">Links include your code <strong>{{ $affiliate->code }}</strong>. Sales from visitors who arrive through your link within {{ app(\App\Support\Settings::class)->get('referral.window_days') }} days are attributed to you (the latest referral link wins).</p>
<div class="card mt-4 flex flex-wrap items-center gap-3 p-4 text-sm" x-data="{ copied: false }">
    <span class="min-w-0 flex-1 truncate font-mono">{{ $referrals->shareUrl($affiliate, route('home')) }}</span>
    <button type="button" class="btn-secondary min-h-10" @click="navigator.clipboard.writeText(@js($referrals->shareUrl($affiliate, route('home')))); copied = true"><x-sj-icon name="copy" class="size-4" /><span x-text="copied ? 'Copied' : 'Copy store link'"></span></button>
</div>
<ul class="mt-6 space-y-3">
    @foreach ($products as $p)
        @php($url = $referrals->shareUrl($affiliate, route('products.show', $p)))
        <li class="card flex items-center gap-3 p-3" x-data="{ copied: false }">
            <div class="size-14 shrink-0 overflow-hidden rounded-lg bg-pearl"><x-product-image :image="$p->mainImage" :alt="$p->name" sizes="56px" /></div>
            <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">{{ $p->name }}</p><p class="truncate text-xs text-muted">{{ $url }}</p></div>
            <button type="button" class="btn-ghost min-h-10 px-3" @click="navigator.clipboard.writeText(@js($url)); copied = true"><x-sj-icon name="copy" class="size-4" /><span class="sr-only" x-text="copied ? 'Copied' : 'Copy link'">Copy link</span></button>
            <a class="btn-ghost min-h-10 px-3" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($p->name.' — '.$url) }}"><x-sj-icon name="whatsapp" class="size-4" /><span class="sr-only">Share on WhatsApp</span></a>
        </li>
    @endforeach
</ul>
<div class="mt-6">{{ $products->links() }}</div>
@endsection
