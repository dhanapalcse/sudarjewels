<?php

namespace App\Domain\Commissions;

use App\Enums\CommissionStatus;
use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\CommissionEntry;
use App\Models\PayoutItem;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    /**
     * Group payable entries into a processing payout. Entries are claimed with a conditional
     * UPDATE (payout_id IS NULL) under row locks, so an entry can never sit in two active payouts.
     *
     * @param  array<int>  $entryIds
     */
    public function create(Affiliate $affiliate, array $entryIds, User $actor, ?string $notes = null): AffiliatePayout
    {
        $entryIds = array_values(array_unique(array_map('intval', $entryIds)));
        if ($entryIds === []) {
            throw new BusinessRuleException('Select at least one payable commission entry.');
        }

        return DB::transaction(function () use ($affiliate, $entryIds, $actor, $notes) {
            $entries = CommissionEntry::whereIn('id', $entryIds)->where('affiliate_id', $affiliate->id)
                ->where('status', CommissionStatus::Payable->value)->whereNull('payout_id')
                ->lockForUpdate()->get();
            if ($entries->count() !== count($entryIds)) {
                throw new BusinessRuleException('Some selected entries are no longer payable or already belong to a payout. Refresh and try again.');
            }
            $total = (int) $entries->sum('amount_paise');
            if ($total <= 0) {
                throw new BusinessRuleException('The selected entries net to zero or less; include more earnings.');
            }

            $payout = AffiliatePayout::create([
                'affiliate_id' => $affiliate->id,
                'status' => PayoutStatus::Processing,
                'method' => $affiliate->payout_method,
                'amount_paise' => $total,
                'destination_snapshot' => $affiliate->maskedPayoutDestination(),
                'notes' => $notes,
                'created_by_id' => $actor->id,
            ]);

            $claimed = CommissionEntry::whereIn('id', $entryIds)->whereNull('payout_id')
                ->where('status', CommissionStatus::Payable->value)
                ->update(['payout_id' => $payout->id, 'updated_at' => now()]);
            if ($claimed !== count($entryIds)) {
                throw new BusinessRuleException('Another payout claimed some of these entries at the same time. Nothing was changed.');
            }
            foreach ($entries as $entry) {
                PayoutItem::create(['affiliate_payout_id' => $payout->id, 'commission_entry_id' => $entry->id, 'amount_paise' => $entry->amount_paise]);
            }
            Audit::log('payout.created', $payout, ['amount_paise' => $total, 'entries' => $entryIds], null, $actor);

            return $payout;
        });
    }

    public function complete(AffiliatePayout $payout, PayoutMethod $method, string $reference, CarbonInterface $paidOn, int $amountPaise, User $actor): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $method, $reference, $paidOn, $amountPaise, $actor) {
            $payout = AffiliatePayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($payout->status === PayoutStatus::Completed) {
                return $payout;
            }
            $payout->status->assertCanTransitionTo(PayoutStatus::Completed);
            if ($amountPaise !== $payout->amount_paise) {
                throw new BusinessRuleException('The paid amount must equal the payout total.');
            }
            $payout->update([
                'status' => PayoutStatus::Completed,
                'method' => $method,
                'reference' => $reference,
                'paid_on' => $paidOn,
                'completed_by_id' => $actor->id,
                'completed_at' => now(),
            ]);
            CommissionEntry::where('payout_id', $payout->id)->update([
                'status' => CommissionStatus::Paid->value, 'paid_at' => now(), 'updated_at' => now(),
            ]);
            Audit::log('payout.completed', $payout, ['method' => $method->value, 'amount_paise' => $amountPaise], null, $actor);

            return $payout;
        });
    }

    /** Failed or cancelled attempts release their entries back to the payable balance. */
    public function abandon(AffiliatePayout $payout, PayoutStatus $status, string $reason, User $actor): AffiliatePayout
    {
        if (! in_array($status, [PayoutStatus::Failed, PayoutStatus::Cancelled], true)) {
            throw new BusinessRuleException('Use complete() for successful payouts.');
        }

        return DB::transaction(function () use ($payout, $status, $reason, $actor) {
            $payout = AffiliatePayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $payout->status->assertCanTransitionTo($status);
            $payout->update(['status' => $status, 'failure_reason' => $reason]);
            CommissionEntry::where('payout_id', $payout->id)->update(['payout_id' => null, 'updated_at' => now()]);
            Audit::log('payout.'.$status->value, $payout, [], $reason, $actor);

            return $payout;
        });
    }
}
