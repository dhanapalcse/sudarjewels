@php
    /** @var \App\Support\Seo|null $seo */
    $seo ??= \App\Support\Seo::make($title ?? $settings->get('business.name'));
    $ga = $settings->ga4Id();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $seo->title }}</title>
@if ($seo->description)<meta name="description" content="{{ $seo->description }}">@endif
@if ($seo->noindex || ! app()->environment('production'))
    <meta name="robots" content="noindex, nofollow">
@endif
@if ($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif
<meta property="og:site_name" content="{{ $settings->get('business.name') }}">
<meta property="og:type" content="{{ $seo->type === 'product' ? 'product' : 'website' }}">
<meta property="og:title" content="{{ $seo->title }}">
@if ($seo->description)<meta property="og:description" content="{{ $seo->description }}">@endif
@if ($seo->canonical)<meta property="og:url" content="{{ $seo->canonical }}">@endif
@if ($seo->image)<meta property="og:image" content="{{ $seo->image }}"><meta name="twitter:card" content="summary_large_image">@endif
<meta name="theme-color" content="#145A58">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
@foreach ($seo->jsonLd as $ld)
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endforeach
@if ($ga && ! ($privateArea ?? false))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($ga), { allow_google_signals: false, allow_ad_personalization_signals: false });
    </script>
@endif
