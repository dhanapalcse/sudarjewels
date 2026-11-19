<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order_number')->url(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r])),
            TextColumn::make('status')->badge(),
            TextColumn::make('grand_total_paise')->label('Total')->formatStateUsing(fn ($state) => Money::format($state)),
            TextColumn::make('created_at')->date('j M Y'),
        ])->defaultSort('id', 'desc');
    }
}
