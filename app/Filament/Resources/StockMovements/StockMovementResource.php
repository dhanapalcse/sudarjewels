<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\Concerns\RequiresPermission;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockMovementResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = StockMovement::class;

    protected static ?string $permission = 'inventory.manage';

    protected static ?string $viewPermission = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variant.product', 'user']))
            ->columns([
                TextColumn::make('created_at')->dateTime('j M Y H:i', config('sudar.business.timezone'))->sortable(),
                TextColumn::make('variant.sku')->label('SKU')->searchable(),
                TextColumn::make('variant.product.name')->label('Product'),
                TextColumn::make('type')->badge(),
                TextColumn::make('quantity')->color(fn ($state) => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('balance_after')->label('On hand after'),
                TextColumn::make('reference')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('note')->limit(40),
            ])
            ->filters([SelectFilter::make('type')->options(StockMovementType::class)])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockMovements::route('/')];
    }
}
