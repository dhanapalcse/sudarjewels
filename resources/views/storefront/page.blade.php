@extends('layouts.app')

@section('content')
<article class="container-page max-w-3xl pt-8">
    <h1 class="display text-4xl sm:text-5xl">{{ $page->title }}</h1>
    @if ($page->needs_review)
        <p class="mt-4 rounded-xl border border-gold bg-pearl px-4 py-3 text-sm" role="note">This page contains placeholder text that the business must replace before launch.</p>
    @endif
    <div class="prose-sj mt-6">{!! \Illuminate\Support\Str::markdown((string) $page->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
    <p class="mt-10 text-xs text-muted">Last updated {{ $page->updated_at?->timezone(config('sudar.business.timezone'))->format('j M Y') }}</p>
</article>
@endsection
