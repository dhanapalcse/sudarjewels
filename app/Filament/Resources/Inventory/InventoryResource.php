<?php

namespace App\Filament\Resources\Inventory;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\ProductVariant;
use App\Support\Settings;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InventoryResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = ProductVariant::class;

    protected static ?string $permission = 'inventory.manage';

    protected static ?string $viewPermission = null;

    protected static ?string $navigationLabel = 'Inventory';

    protected static ?string $modelLabel = 'stock item';

    protected static ?string $slug = 'inventory';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $low = (int) app(Settings::class)->get('inventory.low_stock_threshold');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product'))
            ->columns([
                TextColumn::make('product.name')->searchable()->sortable(),
                TextColumn::make('sku')->searchable(),
                TextColumn::make('label')->state(fn (ProductVariant $r) => $r->displayName()),
                TextColumn::make('stock_on_hand')->label('On hand')->sortable(),
                TextColumn::make('stock_reserved')->label('Reserved')->sortable(),
                TextColumn::make('available')->state(fn (ProductVariant $r) => $r->available())->weight('bold')
                    ->color(fn ($state) => $state <= $low ? 'danger' : 'success'),
            ])
            ->filters([
                Filter::make('low')->label("Low stock (≤ {$low})")->query(fn (Builder $query) => $query->whereRaw('stock_on_hand - stock_reserved <= ?', [$low])->where('is_active', true)),
            ])
            ->recordActions([VariantsRelationManager::adjustStockAction()])
            ->defaultSort('stock_on_hand');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInventory::route('/')];
    }
}
