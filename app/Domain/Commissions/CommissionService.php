<?php

namespace App\Domain\Commissions;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\OrderStatus;
use App\Models\CommissionEntry;
use App\Models\Order;
use App\Models\RefundItem;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Append-only commission ledger. Amounts are never edited after creation;
 * corrections are new reversal/adjustment entries keyed by a unique source_key.
 */
class CommissionService
{
    public function __construct(private Settings $settings) {}

    public function createEarningsForOrder(Order $order, string $suffix = ''): void
    {
        if (! $order->affiliate_id) {
            return;
        }
        foreach ($order->items()->get() as $item) {
            if ($item->commission_paise <= 0) {
                continue;
            }
            $this->insertOnce([
                'affiliate_id' => $order->affiliate_id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'type' => CommissionType::Earning,
                'status' => CommissionStatus::Pending,
                'amount_paise' => $item->commission_paise,
                'rate_bp' => $item->commission_rate_bp,
                'eligible_paise' => $item->commission_eligible_paise,
                'description' => "{$item->quantity} × {$item->label()} ({$item->sku})",
                'source_key' => "earning:item:{$item->id}".($suffix ? ":{$suffix}" : ''),
            ]);
        }
    }

    /** Fully offset every open earning of the order (cancellation or re-attribution). */
    public function reverseForCancellation(Order $order, string $reason = 'cancellation'): void
    {
        $earnings = CommissionEntry::where('order_id', $order->id)->where('type', CommissionType::Earning->value)
            ->where('status', '!=', CommissionStatus::Cancelled->value)->lockForUpdate()->get();
        foreach ($earnings as $earning) {
            $remaining = $earning->amount_paise + $this->offsetsFor($earning);
            if ($remaining > 0) {
                $this->offset($earning, $remaining, "{$reason}:entry:{$earning->id}", ucfirst($reason).' of order '.$order->order_number);
            }
        }
    }

    /**
     * Reverse commission proportionally to the refunded share of the item's line total.
     * Cumulative targets keep rounding consistent across several partial refunds.
     */
    public function reverseForRefundItem(RefundItem $refundItem): void
    {
        $item = $refundItem->orderItem;
        $earning = CommissionEntry::where('order_item_id', $item->id)->where('type', CommissionType::Earning->value)
            ->where('status', '!=', CommissionStatus::Cancelled->value)->latest('id')->lockForUpdate()->first();
        if (! $earning || $item->line_total_paise <= 0) {
            return;
        }
        $refundedSoFar = (int) RefundItem::where('order_item_id', $item->id)->where('id', '<=', $refundItem->id)->sum('amount_paise');
        $targetReversed = Money::proportion($earning->amount_paise, min($refundedSoFar, $item->line_total_paise), $item->line_total_paise);
        $alreadyReversed = -1 * (int) CommissionEntry::where('reverses_entry_id', $earning->id)->where('source_key', 'like', 'refund:%')->sum('amount_paise');
        $amount = min($targetReversed - $alreadyReversed, $earning->amount_paise + $this->offsetsFor($earning));
        if ($amount > 0) {
            $this->offset($earning, $amount, "refund:refund_item:{$refundItem->id}", "Refund on order {$item->order->order_number}: {$item->label()}");
        }
    }

    /**
     * Move pending entries to payable once the order is fully paid, delivered and the
     * configured return window has elapsed. Does nothing until the window is configured.
     */
    public function markEligibleAsPayable(): int
    {
        $window = $this->settings->get('commission.return_window_days');
        if ($window === null || $window === '') {
            Log::warning('Commission eligibility skipped: commission.return_window_days is not configured.');

            return 0;
        }
        $cutoff = now()->subDays((int) $window);

        $orderIds = Order::query()
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Completed->value])
            ->whereNotNull('delivered_at')->where('delivered_at', '<=', $cutoff)
            ->whereColumn('amount_paid_paise', '>=', 'grand_total_paise')
            ->whereHas('commissionEntries', fn ($q) => $q->where('status', CommissionStatus::Pending->value))
            ->pluck('id');

        $count = 0;
        foreach ($orderIds as $orderId) {
            $count += CommissionEntry::where('order_id', $orderId)->where('status', CommissionStatus::Pending->value)
                ->update(['status' => CommissionStatus::Payable->value, 'payable_at' => now(), 'updated_at' => now()]);
        }

        return $count;
    }

    /** @return array{pending:int, payable:int, paid:int, held:int} */
    public function balances(int $affiliateId): array
    {
        $rows = CommissionEntry::where('affiliate_id', $affiliateId)->selectRaw('status, (payout_id is not null) as held, sum(amount_paise) as total')
            ->groupBy('status', DB::raw('(payout_id is not null)'))->get();
        $out = ['pending' => 0, 'payable' => 0, 'paid' => 0, 'held' => 0];
        foreach ($rows as $row) {
            $status = $row->status instanceof CommissionStatus ? $row->status->value : $row->status;
            if ($status === 'payable' && $row->held) {
                $out['held'] += (int) $row->total;
            } elseif (isset($out[$status])) {
                $out[$status] += (int) $row->total;
            }
        }

        return $out;
    }

    private function offsetsFor(CommissionEntry $earning): int
    {
        return (int) CommissionEntry::where('reverses_entry_id', $earning->id)->sum('amount_paise');
    }

    private function offset(CommissionEntry $earning, int $amount, string $sourceKey, string $description): void
    {
        $unpaid = in_array($earning->status, [CommissionStatus::Pending, CommissionStatus::Payable], true) && $earning->payout_id === null;

        $entry = $this->insertOnce([
            'affiliate_id' => $earning->affiliate_id,
            'order_id' => $earning->order_id,
            'order_item_id' => $earning->order_item_id,
            'reverses_entry_id' => $earning->id,
            // Unpaid: offset alongside the earning. Already paid/in payout: deduct from future payouts.
            'type' => $unpaid ? CommissionType::Reversal : CommissionType::Adjustment,
            'status' => $unpaid ? $earning->status : CommissionStatus::Payable,
            'payable_at' => $unpaid ? $earning->payable_at : now(),
            'amount_paise' => -$amount,
            'description' => $description,
            'source_key' => $sourceKey,
        ]);

        if ($entry && $unpaid && $earning->amount_paise + $this->offsetsFor($earning) === 0) {
            // Fully offset before payout: retire both entries, keeping them for history.
            CommissionEntry::where(function ($q) use ($earning) {
                $q->whereKey($earning->id)->orWhere(fn ($q) => $q->where('reverses_entry_id', $earning->id)->where('type', CommissionType::Reversal->value));
            })->whereNull('payout_id')->update(['status' => CommissionStatus::Cancelled->value, 'updated_at' => now()]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function insertOnce(array $attributes): ?CommissionEntry
    {
        if (CommissionEntry::where('source_key', $attributes['source_key'])->exists()) {
            return null;
        }
        try {
            return DB::transaction(fn () => CommissionEntry::create($attributes));
        } catch (UniqueConstraintViolationException) {
            return null; // processed concurrently
        }
    }
}
