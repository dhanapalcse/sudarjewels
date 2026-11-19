@extends('layouts.affiliate')

@section('content')
<h1 class="display text-4xl">Catalogue</h1>
<form method="get" class="mt-4 flex gap-2" role="search"><label for="q" class="sr-only">Search</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Search by name or SKU" class="field"><button class="btn-primary">Search</button></form>
<ul class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
    @foreach ($products as $p)
        @php($rate = $rates->forProduct($p))
        <li class="card overflow-hidden">
            <div class="aspect-square bg-pearl"><x-product-image :image="$p->mainImage" :alt="$p->name" sizes="(min-width: 1024px) 25vw, 50vw" /></div>
            <div class="p-3 text-sm">
                <p class="font-medium">{{ $p->name }}</p>
                <p class="text-muted">{{ \App\Support\Money::format($p->minPrice()) }} · {{ $p->totalAvailable() }} in stock</p>
                <p class="mt-1 text-teal">{{ $rate['enabled'] ? 'Commission '.\App\Support\Money::formatRate($rate['rate_bp']) : 'No commission' }}</p>
            </div>
        </li>
    @endforeach
</ul>
<div class="mt-6">{{ $products->links() }}</div>
@endsection
