<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Inventory\InventoryService;
use App\Filament\Support\Guard;
use App\Filament\Support\MoneyInput;
use App\Models\ProductVariant;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants & stock';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('sku')->label('Variant SKU')->required()->unique(ignoreRecord: true)->maxLength(64),
            TextInput::make('name')->label('Display name (optional)')->maxLength(190),
            Toggle::make('is_active')->default(true)->inline(false),
            TextInput::make('finish')->maxLength(60)->datalist(['Gold', 'Silver', 'Rose gold']),
            TextInput::make('size')->maxLength(40),
            TextInput::make('colour')->maxLength(40),
            MoneyInput::make('price_paise', 'Price override', nullable: true)->helperText('Blank = product price'),
            MoneyInput::make('regular_price_paise', 'Regular price override', nullable: true),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->searchable(),
                TextColumn::make('label')->state(fn (ProductVariant $r) => $r->displayName()),
                TextColumn::make('price')->state(fn (ProductVariant $r) => Money::format($r->effectivePrice())),
                TextColumn::make('stock_on_hand')->label('On hand'),
                TextColumn::make('stock_reserved')->label('Reserved'),
                TextColumn::make('available')->state(fn (ProductVariant $r) => $r->available())->weight('bold'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                self::adjustStockAction(),
                EditAction::make(),
            ]);
    }

    public static function adjustStockAction(): Action
    {
        return Action::make('adjustStock')
            ->label('Adjust stock')
            ->icon('heroicon-o-arrows-up-down')
            ->authorize('inventory.manage')
            ->schema([
                TextInput::make('delta')->label('Change (+ received, − removed)')->integer()->required()->notIn([0]),
                TextInput::make('note')->label('Reason')->required()->maxLength(300),
                Hidden::make('key')->default(fn () => (string) Str::uuid()),
            ])
            ->action(fn (ProductVariant $record, array $data) => Guard::run(
                fn () => app(InventoryService::class)->adjust($record, (int) $data['delta'], 'admin-adjust:'.$data['key'], auth()->user(), $data['note']),
                'Stock updated',
            ));
    }
}
