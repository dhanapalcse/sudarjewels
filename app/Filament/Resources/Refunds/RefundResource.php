<?php

namespace App\Filament\Resources\Refunds;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Refund;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RefundResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = Refund::class;

    protected static ?string $permission = null;

    protected static ?string $viewPermission = 'refunds.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('refunded_at')->dateTime('j M Y H:i', config('sudar.business.timezone'))->sortable(),
            TextColumn::make('order.order_number')->searchable()->url(fn (Refund $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
            TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state)),
            TextColumn::make('method')->badge(),
            TextColumn::make('reason')->limit(50),
            TextColumn::make('recorder.name')->label('By'),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRefunds::route('/')];
    }
}
