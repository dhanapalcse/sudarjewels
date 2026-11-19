<?php

namespace App\Domain\Orders;

use App\Domain\Commissions\CommissionService;
use App\Domain\Customers\CustomerService;
use App\Domain\Inventory\InventoryService;
use App\Domain\Notifications\Notifier;
use App\Domain\Warranty\WarrantyService;
use App\Enums\AttributionSource;
use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Enums\UnitStatus;
use App\Enums\WarrantyStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRevision;
use App\Models\OrderStatusHistory;
use App\Models\PurchasedUnit;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private QuoteCalculator $calculator,
        private InventoryService $inventory,
        private CommissionService $commissions,
        private CustomerService $customers,
        private WarrantyService $warranty,
        private Notifier $notifier,
    ) {}

    /**
     * Create or update an order quote (draft/submitted). Used by admin and affiliate flows.
     *
     * @param  array<string, mixed>  $data  customer + delivery fields, lines, channel, notes
     * @param  array{affiliate?:?Affiliate, attribution_source?:?AttributionSource, referral_code?:?string, allow_overrides?:bool, status?:OrderStatus, idempotency_key?:?string}  $context
     */
    public function saveQuote(?Order $order, array $data, User $actor, array $context = []): Order
    {
        $phone = Phone::normalize($data['customer_phone'] ?? null);
        if (! $phone) {
            throw new BusinessRuleException('Enter a valid 10-digit Indian mobile number.');
        }

        $idempotencyKey = $context['idempotency_key'] ?? null;
        if ($order === null && $idempotencyKey) {
            $existing = Order::where('created_by_id', $actor->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing; // duplicate submission
            }
        }

        if ($order && ! $order->status->isEditableQuote()) {
            throw new BusinessRuleException('Only draft or submitted orders can be edited. Confirmed orders need an admin-reviewed change request.');
        }

        $affiliate = array_key_exists('affiliate', $context) ? $context['affiliate'] : $order?->affiliate;
        $deliveryRequired = (bool) ($data['delivery_required'] ?? true);

        $quote = $this->calculator->calculate($data['lines'] ?? [], [
            'order_discount_paise' => (int) ($data['order_discount_paise'] ?? 0),
            'shipping_paise' => $data['shipping_paise'] ?? null,
            'delivery_required' => $deliveryRequired,
            'with_commission' => $affiliate !== null,
            'allow_overrides' => (bool) ($context['allow_overrides'] ?? false),
        ]);

        if ($deliveryRequired) {
            foreach (['address_line1', 'city', 'state', 'postal_code'] as $field) {
                if (blank($data[$field] ?? null)) {
                    throw new BusinessRuleException('A delivery address (line 1, city, state, PIN code) is required.');
                }
            }
        }

        try {
            return DB::transaction(function () use ($order, $data, $actor, $context, $affiliate, $quote, $phone, $deliveryRequired, $idempotencyKey) {
                $isNew = $order === null;
                $status = $context['status'] ?? ($order?->status ?? OrderStatus::Draft);
                $customer = $this->customers->findOrCreate($data['customer_name'], $phone, $data['customer_email'] ?? null);

                $order ??= new Order([
                    'created_by_id' => $actor->id,
                    'idempotency_key' => $idempotencyKey,
                    'payment_status' => PaymentStatus::Unpaid,
                    'fulfilment_status' => FulfilmentStatus::Unfulfilled,
                ]);
                if (! $isNew) {
                    $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                    if (! $order->status->isEditableQuote()) {
                        throw new BusinessRuleException('This order was confirmed in the meantime and can no longer be edited.');
                    }
                }

                $order->fill([
                    'customer_id' => $customer->id,
                    'customer_name' => trim($data['customer_name']),
                    'customer_phone' => $phone,
                    'customer_email' => $data['customer_email'] ?? null,
                    'delivery_required' => $deliveryRequired,
                    'shipping_name' => $data['shipping_name'] ?? $data['customer_name'],
                    'shipping_phone' => Phone::normalize($data['shipping_phone'] ?? null) ?? $phone,
                    'address_line1' => $data['address_line1'] ?? null,
                    'address_line2' => $data['address_line2'] ?? null,
                    'landmark' => $data['landmark'] ?? null,
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'channel' => $data['channel'] ?? ($order->channel ?? SalesChannel::WhatsApp),
                    'customer_notes' => $data['customer_notes'] ?? $order->customer_notes,
                    'internal_notes' => $data['internal_notes'] ?? $order->internal_notes,
                    'order_discount_paise' => $quote['totals']['order_discount_paise'],
                    'subtotal_paise' => $quote['totals']['subtotal_paise'],
                    'discount_total_paise' => $quote['totals']['discount_total_paise'],
                    'tax_paise' => $quote['totals']['tax_paise'],
                    'shipping_paise' => $quote['totals']['shipping_paise'],
                    'grand_total_paise' => $quote['totals']['grand_total_paise'],
                    'commission_estimate_paise' => $quote['totals']['commission_estimate_paise'],
                ]);

                if (array_key_exists('affiliate', $context)) {
                    $order->affiliate_id = $affiliate?->id;
                    $order->attribution_source = $affiliate ? ($context['attribution_source'] ?? AttributionSource::AdminAssigned) : null;
                    $order->referral_code = $affiliate ? ($context['referral_code'] ?? $affiliate->code) : null;
                    $order->attributed_at = $affiliate ? now() : null;
                }

                $previousStatus = $order->exists ? $order->status : null;
                $order->status = $status;
                if ($status === OrderStatus::Submitted && ! $order->submitted_at) {
                    $order->submitted_at = now();
                }
                $order->save();

                $order->items()->delete();
                foreach ($quote['items'] as $item) {
                    $order->items()->create(Arr::except($item, ['available', 'price_overridden']));
                }

                if ($isNew) {
                    $this->history($order, 'status', null, $status->value, 'Order created', $actor);
                } elseif ($previousStatus !== $status) {
                    $this->history($order, 'status', $previousStatus?->value, $status->value, null, $actor);
                } else {
                    $this->history($order, 'quote', null, null, 'Order details updated', $actor);
                }

                return $order->refresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey) {
                return Order::where('created_by_id', $actor->id)->where('idempotency_key', $idempotencyKey)->firstOrFail();
            }
            throw $e;
        }
    }

    public function submit(Order $order, User $actor): Order
    {
        return $this->transition($order, OrderStatus::Submitted, $actor, fn (Order $o) => $o->submitted_at ??= now());
    }

    /**
     * Compare the stored quote with current catalogue data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function quoteDifferences(Order $order): array
    {
        $order->loadMissing('items');
        $lines = $order->items->map(fn (OrderItem $i) => ['variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->all();
        $differences = [];
        try {
            $fresh = $this->calculator->calculate($lines, ['with_commission' => $order->affiliate_id !== null, 'delivery_required' => $order->delivery_required]);
        } catch (BusinessRuleException $e) {
            return [['item' => null, 'field' => 'availability', 'from' => 'available', 'to' => $e->getMessage()]];
        }
        foreach ($order->items->values() as $idx => $item) {
            $now = $fresh['items'][$idx];
            foreach (['unit_price_paise' => 'price', 'commission_rate_bp' => 'commission rate', 'commission_enabled' => 'commission enabled'] as $field => $label) {
                if ((int) $item->{$field} !== (int) $now[$field]) {
                    $differences[] = ['item' => $item->label(), 'sku' => $item->sku, 'field' => $label, 'from' => $item->{$field}, 'to' => $now[$field]];
                }
            }
        }

        return $differences;
    }

    /** Re-price a submitted affiliate quote from the live catalogue, recording a visible revision. */
    public function reviseQuote(Order $order, User $actor, ?string $reason = null): Order
    {
        if (! $order->status->isEditableQuote()) {
            throw new BusinessRuleException('Only unconfirmed quotes can be revised.');
        }
        $differences = $this->quoteDifferences($order);
        if ($differences === []) {
            return $order;
        }

        return DB::transaction(function () use ($order, $actor, $reason, $differences) {
            $before = ['grand_total_paise' => $order->grand_total_paise, 'commission_estimate_paise' => $order->commission_estimate_paise];
            $lines = $order->items->map(fn (OrderItem $i) => ['variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->all();
            $order = $this->saveQuote($order, $this->orderDataForRequote($order, $lines), $actor, []);
            $order->quote_revision += 1;
            $order->quote_revised_at = now();
            $order->save();

            OrderRevision::create([
                'order_id' => $order->id,
                'revision' => $order->quote_revision,
                'changes' => [
                    'items' => $differences,
                    'totals' => ['before' => $before, 'after' => ['grand_total_paise' => $order->grand_total_paise, 'commission_estimate_paise' => $order->commission_estimate_paise]],
                ],
                'reason' => $reason,
                'user_id' => $actor->id,
            ]);
            $this->history($order, 'quote', null, 'revision '.$order->quote_revision, $reason ?: 'Quote revised to current prices', $actor);
            if ($order->affiliate) {
                $this->notifier->affiliateQuoteRevised($order);
            }

            return $order;
        });
    }

    /**
     * Confirm an order: revalidate quote and stock, reserve stock atomically,
     * create purchased units and pending commission. Safe to retry.
     */
    public function confirm(Order $order, User $actor): Order
    {
        if ($order->status === OrderStatus::Confirmed || $order->status === OrderStatus::Completed) {
            return $order; // idempotent retry
        }
        $order->status->assertCanTransitionTo(OrderStatus::Confirmed);

        if ($order->channel === SalesChannel::Affiliate) {
            $differences = $this->quoteDifferences($order);
            if ($differences !== []) {
                throw new QuoteChanged($differences);
            }
        }
        if ($order->affiliate_id && ! $order->affiliate?->isActive()) {
            throw new BusinessRuleException('The assigned affiliate is not active. Reassign or remove the affiliate before confirming.');
        }

        $confirmed = DB::transaction(function () use ($order, $actor) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === OrderStatus::Confirmed) {
                return $locked;
            }
            $locked->status->assertCanTransitionTo(OrderStatus::Confirmed);
            $updated = Order::whereKey($locked->id)->whereIn('status', [OrderStatus::Draft->value, OrderStatus::Submitted->value])
                ->update(['status' => OrderStatus::Confirmed->value, 'confirmed_at' => now(), 'updated_at' => now()]);
            if ($updated !== 1) {
                return $locked->refresh();
            }
            $locked->refresh()->load('items.variant');

            $this->inventory->reserveForOrder($locked);

            foreach ($locked->items as $item) {
                for ($n = 1; $n <= $item->quantity; $n++) {
                    PurchasedUnit::firstOrCreate(
                        ['order_item_id' => $item->id, 'unit_number' => $n],
                        [
                            'order_id' => $locked->id,
                            'code' => $locked->order_number.'-'.$item->id.'-'.$n,
                            'status' => UnitStatus::Allocated,
                            'warranty_eligible' => $item->warranty_eligible,
                            'warranty_months' => $item->warranty_months,
                            'warranty_terms' => $item->warranty_terms,
                            'warranty_status' => $item->warranty_eligible ? WarrantyStatus::Unregistered : WarrantyStatus::NotEligible,
                        ],
                    );
                }
            }

            $this->commissions->createEarningsForOrder($locked);
            $this->history($locked, 'status', $order->status->value, OrderStatus::Confirmed->value, null, $actor);

            return $locked;
        });

        DB::afterCommit(fn () => $this->notifier->orderConfirmed($confirmed));

        return $confirmed;
    }

    public function cancel(Order $order, User $actor, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === OrderStatus::Cancelled) {
                return $locked;
            }
            $locked->status->assertCanTransitionTo(OrderStatus::Cancelled);
            $shipped = $locked->units()->whereIn('status', [UnitStatus::Dispatched->value, UnitStatus::Delivered->value])->exists();
            if ($shipped) {
                throw new BusinessRuleException('Units have already been dispatched. Record a return and refund instead of cancelling.');
            }
            if ($locked->amount_paid_paise - $locked->amount_refunded_paise > 0) {
                throw new BusinessRuleException('Refund the payments received before cancelling this order.');
            }
            $from = $locked->status;
            $locked->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);

            if ($from === OrderStatus::Confirmed) {
                $this->inventory->releaseForOrder($locked);
                $locked->units()->where('status', UnitStatus::Allocated->value)->update([
                    'status' => UnitStatus::Cancelled->value,
                    'warranty_status' => WarrantyStatus::Void->value,
                ]);
                $this->commissions->reverseForCancellation($locked);
            }
            $this->history($locked, 'status', $from->value, OrderStatus::Cancelled->value, $reason, $actor);

            return $locked;
        });
    }

    public function complete(Order $order, User $actor): Order
    {
        if ($order->fulfilment_status !== FulfilmentStatus::Delivered && $order->fulfilment_status !== FulfilmentStatus::Returned) {
            throw new BusinessRuleException('Only delivered orders can be completed.');
        }

        return $this->transition($order, OrderStatus::Completed, $actor, fn (Order $o) => $o->completed_at = now());
    }

    public function setProcessing(Order $order, User $actor, bool $processing): Order
    {
        $to = $processing ? FulfilmentStatus::Processing : FulfilmentStatus::Unfulfilled;
        if ($order->status !== OrderStatus::Confirmed) {
            throw new BusinessRuleException('Only confirmed orders can be packed.');
        }
        $order->fulfilment_status->assertCanTransitionTo($to);
        $from = $order->fulfilment_status;
        $order->update(['fulfilment_status' => $to]);
        $this->history($order, 'fulfilment_status', $from->value, $to->value, null, $actor);

        return $order;
    }

    /** Change attribution. After confirmation this rewrites commission and is audited. */
    public function changeAttribution(Order $order, ?Affiliate $affiliate, User $actor, string $reason): Order
    {
        if ($affiliate && ! $affiliate->isActive()) {
            throw new BusinessRuleException('Inactive or unapproved affiliates cannot be assigned.');
        }
        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
            throw new BusinessRuleException('Attribution cannot be changed on cancelled or completed orders.');
        }

        return DB::transaction(function () use ($order, $affiliate, $actor, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $before = ['affiliate_id' => $order->affiliate_id, 'attribution_source' => $order->attribution_source?->value];
            if ($order->status === OrderStatus::Confirmed) {
                $this->commissions->reverseForCancellation($order, 'reattribution');
            }
            $order->update([
                'affiliate_id' => $affiliate?->id,
                'attribution_source' => $affiliate ? AttributionSource::AdminAssigned : null,
                'referral_code' => $affiliate?->code,
                'attributed_at' => $affiliate ? now() : null,
            ]);
            $order->load('items');
            $estimate = 0;
            foreach ($order->items as $item) {
                $commission = ($affiliate && $item->commission_enabled) ? Money::applyRate($item->commission_eligible_paise, $item->commission_rate_bp) : 0;
                $item->update(['commission_paise' => $commission]);
                $estimate += $commission;
            }
            $order->update(['commission_estimate_paise' => $estimate]);
            if ($order->status === OrderStatus::Confirmed && $affiliate) {
                $this->commissions->createEarningsForOrder($order->refresh(), 'r'.now()->format('YmdHis'));
            }
            $this->history($order, 'attribution', (string) ($before['affiliate_id'] ?? '—'), (string) ($affiliate?->code ?? '—'), $reason, $actor);
            Audit::log('order.attribution_changed', $order, ['from' => $before, 'to' => ['affiliate_id' => $affiliate?->id]], $reason, $actor);

            return $order;
        });
    }

    /** Derive order-level fulfilment status from its units. */
    public function refreshFulfilmentStatus(Order $order, ?Model $actor = null): void
    {
        $units = $order->units()->get();
        $relevant = $units->reject(fn (PurchasedUnit $u) => in_array($u->status, [UnitStatus::Cancelled, UnitStatus::Refunded], true) && ! $u->dispatched_at);
        if ($relevant->isEmpty()) {
            return;
        }
        $active = $relevant->filter(fn (PurchasedUnit $u) => $u->status->isActive());

        $to = $order->fulfilment_status;
        if ($active->isEmpty()) {
            $to = FulfilmentStatus::Returned;
        } elseif ($active->every(fn (PurchasedUnit $u) => $u->status === UnitStatus::Delivered)) {
            $to = FulfilmentStatus::Delivered;
        } elseif ($active->contains(fn (PurchasedUnit $u) => in_array($u->status, [UnitStatus::Dispatched, UnitStatus::Delivered], true))) {
            $to = FulfilmentStatus::Dispatched;
        }

        if ($to !== $order->fulfilment_status) {
            $from = $order->fulfilment_status;
            $order->fulfilment_status = $to;
            if ($to === FulfilmentStatus::Delivered) {
                $order->delivered_at = $active->max('delivered_at');
            }
            $order->save();
            $this->history($order, 'fulfilment_status', $from->value, $to->value, null, $actor);
        }
    }

    public function history(Order $order, string $field, ?string $from, ?string $to, ?string $note = null, ?Model $actor = null): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'field' => $field,
            'from' => $from,
            'to' => $to,
            'note' => $note,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
        ]);
    }

    private function transition(Order $order, OrderStatus $to, User $actor, ?callable $mutate = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $mutate) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $to) {
                return $locked;
            }
            $from = $locked->status;
            $from->assertCanTransitionTo($to);
            $locked->status = $to;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();
            $this->history($locked, 'status', $from->value, $to->value, null, $actor);

            return $locked;
        });
    }

    /** @return array<string, mixed> */
    private function orderDataForRequote(Order $order, array $lines): array
    {
        return $order->only(['customer_name', 'customer_phone', 'customer_email', 'delivery_required', 'shipping_name', 'shipping_phone', 'address_line1', 'address_line2', 'landmark', 'city', 'state', 'postal_code', 'channel', 'customer_notes', 'internal_notes'])
            + ['lines' => $lines];
    }
}
