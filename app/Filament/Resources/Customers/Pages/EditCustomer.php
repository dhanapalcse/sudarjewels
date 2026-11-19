<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['phone'], $data['marketing_opt_in']);

        return $data;
    }

    protected function afterSave(): void
    {
        Audit::log('customer.updated', $this->record, ['name' => $this->record->name]);
    }
}
