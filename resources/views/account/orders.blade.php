@extends('layouts.account')

@section('account')
<h1 class="display text-4xl">My orders</h1>
@include('account.partials.order-list', ['orders' => $orders])
<div class="mt-6">{{ $orders->links() }}</div>
@endsection
