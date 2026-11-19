<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Refund;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('items.orderItem'))->columns([
            TextColumn::make('refunded_at')->dateTime('j M Y H:i', config('sudar.business.timezone')),
            TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state)),
            TextColumn::make('method')->badge(),
            TextColumn::make('reason')->limit(40),
            TextColumn::make('lines')->state(fn (Refund $r) => $r->items->map(fn ($i) => "{$i->quantity} × {$i->orderItem->sku}")->implode(', ') ?: 'Amount only'),
            TextColumn::make('recorder.name')->label('By'),
        ])->paginated(false);
    }
}
