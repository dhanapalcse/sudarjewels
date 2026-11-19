<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('paid_at')->dateTime('j M Y H:i', config('sudar.business.timezone')),
            TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state)),
            TextColumn::make('method')->badge(),
            TextColumn::make('reference')->placeholder('—'),
            TextColumn::make('recorder.name')->label('Recorded by'),
        ])->paginated(false);
    }
}
