<?php

namespace App\Domain\Notifications;

use App\Jobs\SendTransactionalSms;
use App\Mail\AffiliateQuoteRevised;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\WarrantyClaim;
use App\Models\WarrantyRegistration;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;

/**
 * Transactional messages (always sent, independent of marketing consent).
 * Each message has a dedupe key so retries and repeated events send once.
 */
class Notifier
{
    public function orderConfirmed(Order $order): void
    {
        SendTransactionalSms::dispatch($order->customer_phone, 'order_confirmed', [
            'order' => $order->order_number, 'total' => Money::format($order->grand_total_paise),
        ], Order::class, $order->id, "order_confirmed:{$order->id}");
    }

    public function orderDispatched(Order $order, Shipment $shipment): void
    {
        SendTransactionalSms::dispatch($order->customer_phone, 'order_dispatched', [
            'order' => $order->order_number,
            'tracking' => $shipment->tracking_number ? " ({$shipment->courier} {$shipment->tracking_number})" : '',
        ], Order::class, $order->id, "order_dispatched:{$shipment->id}");
    }

    public function warrantyRegistered(WarrantyRegistration $registration): void
    {
        SendTransactionalSms::dispatch($registration->registered_phone, 'warranty_registered', [
            'order' => $registration->order->order_number,
            'url' => route('account.warranties'),
        ], WarrantyRegistration::class, $registration->id, "warranty_registered:{$registration->id}");
    }

    public function claimUpdated(WarrantyClaim $claim): void
    {
        $claim->loadMissing('customer');
        SendTransactionalSms::dispatch($claim->customer->phone, 'claim_updated', [
            'claim' => $claim->claim_number, 'status' => $claim->status->label(), 'url' => route('account.claims.show', $claim),
        ], WarrantyClaim::class, $claim->id, "claim_updated:{$claim->id}:{$claim->status->value}:".$claim->updated_at?->timestamp);
    }

    public function affiliateQuoteRevised(Order $order): void
    {
        $email = $order->affiliate?->user?->email;
        if ($email) {
            Mail::to($email)->queue(new AffiliateQuoteRevised($order));
        }
    }
}
