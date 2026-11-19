<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->is(auth()->user()) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            $data['is_active'] = true; // cannot deactivate yourself
        }

        return $data;
    }

    protected function afterSave(): void
    {
        Audit::log('staff.updated', $this->record, ['roles' => $this->record->roles()->pluck('name')->all(), 'is_active' => $this->record->is_active, 'password_changed' => filled($this->data['password'] ?? null)]);
    }
}
