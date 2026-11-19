<?php

namespace App\Filament\Resources\Claims\Pages;

use App\Domain\Warranty\ClaimService;
use App\Enums\ClaimStatus;
use App\Filament\Resources\Claims\ClaimResource;
use App\Filament\Support\Guard;
use App\Models\ClaimUpdate;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewClaim extends ViewRecord
{
    protected static string $resource = ClaimResource::class;

    private function move(ClaimStatus $to, ?string $message, array $decision = []): void
    {
        Guard::run(fn () => app(ClaimService::class)->transition($this->record->refresh(), $to, auth()->user(), $message, $decision), 'Claim updated — the customer has been notified');
    }

    protected function getHeaderActions(): array
    {
        $can = fn (ClaimStatus $state) => fn () => $this->record->status->canTransitionTo($state);

        return [
            Action::make('review')->label('Start review')->authorize('claims.manage')->visible($can(ClaimStatus::UnderReview))
                ->action(fn () => $this->move(ClaimStatus::UnderReview, 'We are reviewing your claim.')),
            Action::make('info')->label('Request information')->color('warning')->authorize('claims.manage')->visible($can(ClaimStatus::MoreInfoRequired))
                ->schema([Textarea::make('message')->label('What do you need from the customer?')->required()])
                ->action(fn (array $data) => $this->move(ClaimStatus::MoreInfoRequired, $data['message'])),
            Action::make('approve')->label('Approve')->color('success')->authorize('claims.manage')->visible($can(ClaimStatus::Approved))
                ->schema([
                    Select::make('resolution')->options(['repair' => 'Repair', 'replacement' => 'Replacement', 'refund' => 'Refund', 'other' => 'Other'])->required(),
                    Textarea::make('decision_reason')->label('Reason (shown to customer)')->required(),
                ])
                ->action(fn (array $data) => $this->move(ClaimStatus::Approved, $data['decision_reason'], $data)),
            Action::make('reject')->label('Reject')->color('danger')->authorize('claims.manage')->visible($can(ClaimStatus::Rejected))
                ->schema([Textarea::make('decision_reason')->label('Reason (shown to customer)')->required()])
                ->action(fn (array $data) => $this->move(ClaimStatus::Rejected, $data['decision_reason'], $data)),
            ActionGroup::make([
                Action::make('replace')->label('Issue replacement piece')->authorize('claims.manage')
                    ->visible(fn () => $this->record->status === ClaimStatus::Approved && ! $this->record->replacement_unit_id)
                    ->schema([
                        Select::make('variant_id')->label('Replacement variant (stock is deducted)')->required()->searchable()
                            ->default(fn () => $this->record->unit->orderItem->product_variant_id)
                            ->options(fn () => ProductVariant::with('product')->where('is_active', true)->get()->mapWithKeys(fn ($v) => [$v->id => "{$v->product->name} — {$v->displayName()} ({$v->sku}) · {$v->available()} available"])),
                    ])
                    ->action(fn (array $data) => Guard::run(fn () => app(ClaimService::class)->issueReplacement($this->record->refresh(), ProductVariant::findOrFail($data['variant_id']), auth()->user()), 'Replacement issued and linked to the original piece')),
                Action::make('resolve')->label('Mark resolved')->authorize('claims.manage')->visible($can(ClaimStatus::Resolved))
                    ->schema([Textarea::make('resolution_notes')->label('Resolution notes (shown to customer)')->required()])
                    ->action(fn (array $data) => $this->move(ClaimStatus::Resolved, $data['resolution_notes'], $data)),
                Action::make('note')->label('Add internal note')->authorize('claims.manage')
                    ->schema([Textarea::make('message')->required()])
                    ->action(fn (array $data) => ClaimUpdate::create(['warranty_claim_id' => $this->record->id, 'author_type' => 'user', 'author_id' => auth()->id(), 'message' => $data['message'], 'visible_to_customer' => false])),
            ])->label('More')->button()->color('gray'),
        ];
    }
}
