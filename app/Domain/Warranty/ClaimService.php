<?php

namespace App\Domain\Warranty;

use App\Domain\Inventory\InventoryService;
use App\Domain\Notifications\Notifier;
use App\Enums\ClaimStatus;
use App\Enums\StockMovementType;
use App\Enums\UnitStatus;
use App\Enums\WarrantyStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClaimAttachment;
use App\Models\ClaimUpdate;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\PurchasedUnit;
use App\Models\User;
use App\Models\WarrantyClaim;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClaimService
{
    public const ATTACHMENT_DISK = 'local'; // private disk, never publicly served

    public function __construct(private InventoryService $inventory, private Settings $settings, private Notifier $notifier) {}

    /** @param array<UploadedFile> $photos */
    public function submit(Customer $customer, PurchasedUnit $unit, string $description, array $photos = []): WarrantyClaim
    {
        $unit->loadMissing('order', 'registration');
        if ($unit->order->customer_id !== $customer->id || ! $unit->registration) {
            throw new BusinessRuleException('This item is not registered to your account.');
        }
        if ($unit->warranty_status !== WarrantyStatus::Registered) {
            throw new BusinessRuleException('This item does not have an active registered warranty.');
        }
        if (! $unit->isCovered()) {
            throw new BusinessRuleException('The warranty for this item has expired.');
        }
        if ($unit->claims()->get()->contains(fn (WarrantyClaim $c) => $c->status->isOpen())) {
            throw new BusinessRuleException('There is already an open claim for this item.');
        }

        return DB::transaction(function () use ($customer, $unit, $description, $photos) {
            $claim = WarrantyClaim::create([
                'purchased_unit_id' => $unit->id,
                'customer_id' => $customer->id,
                'warranty_registration_id' => $unit->warranty_registration_id,
                'status' => ClaimStatus::Submitted,
                'issue_description' => $description,
            ]);
            $update = ClaimUpdate::create([
                'warranty_claim_id' => $claim->id,
                'author_type' => $customer->getMorphClass(),
                'author_id' => $customer->id,
                'status_to' => ClaimStatus::Submitted,
                'message' => $description,
            ]);
            $this->storePhotos($claim, $update, $photos);

            return $claim->refresh();
        });
    }

    /** @param array<UploadedFile> $photos */
    public function customerReply(WarrantyClaim $claim, Customer $customer, string $message, array $photos = []): void
    {
        if ($claim->customer_id !== $customer->id) {
            throw new BusinessRuleException('Claim not found.');
        }
        if ($claim->status !== ClaimStatus::MoreInfoRequired) {
            throw new BusinessRuleException('This claim is not waiting for more information.');
        }
        DB::transaction(function () use ($claim, $customer, $message, $photos) {
            $update = ClaimUpdate::create([
                'warranty_claim_id' => $claim->id,
                'author_type' => $customer->getMorphClass(),
                'author_id' => $customer->id,
                'status_from' => $claim->status,
                'status_to' => ClaimStatus::UnderReview,
                'message' => $message,
            ]);
            $claim->update(['status' => ClaimStatus::UnderReview]);
            $this->storePhotos($claim, $update, $photos);
        });
    }

    public function transition(WarrantyClaim $claim, ClaimStatus $to, User $actor, ?string $message = null, array $decision = []): WarrantyClaim
    {
        $claim->status->assertCanTransitionTo($to);
        if (in_array($to, [ClaimStatus::Approved, ClaimStatus::Rejected], true) && blank($decision['decision_reason'] ?? $message)) {
            throw new BusinessRuleException('Record a reason for the decision.');
        }

        DB::transaction(function () use ($claim, $to, $actor, $message, $decision) {
            $from = $claim->status;
            $attributes = ['status' => $to];
            if (in_array($to, [ClaimStatus::Approved, ClaimStatus::Rejected], true)) {
                $attributes += ['decision_reason' => $decision['decision_reason'] ?? $message, 'decided_by_id' => $actor->id, 'decided_at' => now()];
            }
            if ($to === ClaimStatus::Resolved) {
                $attributes += ['resolution' => $decision['resolution'] ?? $claim->resolution, 'resolution_notes' => $decision['resolution_notes'] ?? $message, 'resolved_at' => now()];
            }
            if (isset($decision['resolution']) && $to === ClaimStatus::Approved) {
                $attributes['resolution'] = $decision['resolution'];
            }
            $claim->update($attributes);
            ClaimUpdate::create([
                'warranty_claim_id' => $claim->id,
                'author_type' => $actor->getMorphClass(),
                'author_id' => $actor->id,
                'status_from' => $from,
                'status_to' => $to,
                'message' => $message,
                'visible_to_customer' => true,
            ]);
            Audit::log('claim.'.$to->value, $claim, ['from' => $from->value, 'to' => $to->value], $message, $actor);
        });
        DB::afterCommit(fn () => $this->notifier->claimUpdated($claim));

        return $claim->refresh();
    }

    /**
     * Issue a replacement unit for an approved claim. The replacement links to the original unit,
     * consumes stock from the chosen variant, and gets coverage per the configured policy (saved on the unit).
     */
    public function issueReplacement(WarrantyClaim $claim, ProductVariant $variant, User $actor): PurchasedUnit
    {
        if ($claim->status !== ClaimStatus::Approved) {
            throw new BusinessRuleException('Approve the claim before issuing a replacement.');
        }
        if ($claim->replacement_unit_id) {
            return $claim->replacementUnit;
        }

        return DB::transaction(function () use ($claim, $variant, $actor) {
            $original = PurchasedUnit::whereKey($claim->purchased_unit_id)->lockForUpdate()->firstOrFail();
            $policy = (string) $this->settings->get('warranty.replacement_policy');
            $this->inventory->adjust($variant, -1, "replacement:claim:{$claim->id}", $actor, "Replacement for claim {$claim->claim_number}", StockMovementType::ReplacementIssue);

            $nextNumber = (int) PurchasedUnit::where('order_item_id', $original->order_item_id)->max('unit_number') + 1;
            [$start, $end] = WarrantyCalculator::replacementCoverage(now(), $original->warranty_months, $policy, $original->warranty_ends_at);
            $replacement = PurchasedUnit::create([
                'order_id' => $original->order_id,
                'order_item_id' => $original->order_item_id,
                'unit_number' => $nextNumber,
                'code' => $original->code.'-R'.$claim->id,
                'status' => UnitStatus::Delivered,
                'dispatched_at' => now(),
                'delivered_at' => now(),
                'warranty_eligible' => true,
                'warranty_months' => $original->warranty_months,
                'warranty_terms' => $original->warranty_terms,
                'warranty_status' => WarrantyStatus::Registered,
                'warranty_starts_at' => $start,
                'warranty_ends_at' => $end,
                'warranty_registration_id' => $original->warranty_registration_id,
                'warranty_registered_at' => now(),
                'replacement_of_unit_id' => $original->id,
                'replacement_policy' => $policy,
            ]);
            $original->update(['warranty_status' => WarrantyStatus::Void]);
            $claim->update(['replacement_unit_id' => $replacement->id, 'resolution' => 'replacement']);
            Audit::log('claim.replacement_issued', $claim, ['replacement_unit_id' => $replacement->id, 'variant_id' => $variant->id, 'policy' => $policy], null, $actor);

            return $replacement;
        });
    }

    /** @param array<UploadedFile> $photos */
    private function storePhotos(WarrantyClaim $claim, ClaimUpdate $update, array $photos): void
    {
        foreach (array_slice($photos, 0, 5) as $photo) {
            if (! $photo instanceof UploadedFile || ! $photo->isValid()) {
                continue;
            }
            $ext = match ($photo->getMimeType()) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $path = $photo->storeAs('claims/'.$claim->id, Str::random(40).'.'.$ext, self::ATTACHMENT_DISK);
            ClaimAttachment::create([
                'warranty_claim_id' => $claim->id,
                'claim_update_id' => $update->id,
                'disk' => self::ATTACHMENT_DISK,
                'path' => $path,
                'original_name' => mb_substr($photo->getClientOriginalName(), 0, 200),
                'mime' => $photo->getMimeType(),
                'size' => $photo->getSize(),
            ]);
        }
    }
}
