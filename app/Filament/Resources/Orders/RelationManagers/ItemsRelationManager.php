<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\OrderItem;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items (purchase snapshot)';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $m = fn ($state) => Money::format((int) $state);

        return $table->columns([
            TextColumn::make('product_name')->description(fn (OrderItem $r) => $r->variant_name.' · '.$r->sku),
            TextColumn::make('quantity'),
            TextColumn::make('unit_price_paise')->label('Unit')->formatStateUsing($m),
            TextColumn::make('allocated_discount_paise')->label('Discount')->formatStateUsing($m),
            TextColumn::make('tax_paise')->label('Tax')->formatStateUsing($m),
            TextColumn::make('line_total_paise')->label('Total')->formatStateUsing($m),
            TextColumn::make('commission_rate_bp')->label('Rate')->formatStateUsing(fn ($state, OrderItem $r) => $r->commission_enabled ? Money::formatRate($state) : 'Off'),
            TextColumn::make('commission_paise')->label('Commission')->formatStateUsing($m),
            TextColumn::make('warranty_months')->label('Warranty')->formatStateUsing(fn ($state, OrderItem $r) => $r->warranty_eligible ? "{$state} mo" : 'None'),
        ])->paginated(false);
    }
}
