@extends('layouts.affiliate')

@section('content')
<h1 class="display text-4xl">Help</h1>
<div class="prose-sj mt-6 max-w-2xl">
    <h2>Creating an order</h2>
    <p>Choose products and quantities, enter the customer's details, then review the total and your estimated commission. The team confirms the order, checks stock and records payment and delivery.</p>
    <h2>Editing</h2>
    <p>You can edit an order until it is confirmed. After confirmation, send a change request from the order page.</p>
    <h2>Quote revisions</h2>
    <p>If prices or commission rates change before confirmation, the team records a revised quote. You'll see it on the order and by email — please let the customer know.</p>
    <h2>Commission</h2>
    <p>Commission is calculated on the item amount after discounts, excluding shipping and tax. It is pending after confirmation and becomes payable once the order is fully paid, delivered and the return window has passed. Refunds reduce commission; refunds after payout are deducted from future earnings.</p>
    <h2>Referral links</h2>
    <p>Share links from the Share page. Your code is included in WhatsApp enquiries from visitors who used your link.</p>
    <p>Read the full <a href="{{ route('pages.affiliate-terms') }}">affiliate terms</a> or <a href="{{ route('pages.contact') }}">contact us</a>.</p>
</div>
@endsection
