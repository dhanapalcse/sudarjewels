<?php

namespace App\Filament\Resources\Affiliates\RelationManagers;

use App\Filament\Resources\CommissionEntries\CommissionEntryResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class CommissionEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionEntries';

    protected static ?string $title = 'Commission ledger';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return CommissionEntryResource::table($table);
    }
}
