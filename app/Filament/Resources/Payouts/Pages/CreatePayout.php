<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Domain\Commissions\PayoutService;
use App\Filament\Resources\Payouts\PayoutResource;
use App\Filament\Support\Guard;
use App\Models\Affiliate;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayout extends CreateRecord
{
    protected static string $resource = PayoutResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return Guard::run(fn () => app(PayoutService::class)->create(Affiliate::findOrFail($data['affiliate_id']), (array) $data['entry_ids'], auth()->user(), $data['notes'] ?? null));
    }

    protected function getRedirectUrl(): string
    {
        return PayoutResource::getUrl('view', ['record' => $this->record]);
    }
}
