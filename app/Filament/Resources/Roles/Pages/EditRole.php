<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('role.permissions_updated', $this->record, ['permissions' => $this->record->permissions()->pluck('name')->all()]);
    }
}
