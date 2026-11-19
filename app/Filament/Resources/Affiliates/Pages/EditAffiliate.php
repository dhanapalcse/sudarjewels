<?php

namespace App\Filament\Resources\Affiliates\Pages;

use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_name'] = $this->record->user->name;
        $data['user_email'] = $this->record->user->email;
        if (! AffiliateResource::canSeePayoutDetails()) {
            foreach (['payout_upi_id', 'payout_account_name', 'payout_account_number', 'payout_ifsc', 'payout_method'] as $f) {
                unset($data[$f]);
            }
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->user->update(['name' => $data['user_name'], 'email' => $data['user_email']]);
        $fields = ['code', 'phone', 'city', 'social_handle', 'admin_notes'];
        $payoutFields = ['payout_method', 'payout_upi_id', 'payout_account_name', 'payout_account_number', 'payout_ifsc'];
        if (AffiliateResource::canSeePayoutDetails()) {
            $fields = array_merge($fields, $payoutFields);
        }
        $record->fill(collect($data)->only($fields)->all());
        $payoutChanged = $record->isDirty($payoutFields);
        $changes = Audit::diff($record, ['code', 'phone']);
        if ($payoutChanged) {
            $record->payout_details_updated_at = now();
            $changes['payout_details'] = 'changed';
        }
        $record->save();
        Audit::log('affiliate.updated', $record, $changes);

        return $record;
    }
}
