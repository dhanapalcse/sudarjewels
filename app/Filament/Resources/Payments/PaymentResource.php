<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Payment;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = Payment::class;

    protected static ?string $permission = null;

    protected static ?string $viewPermission = 'payments.manage';

    protected static ?string $navigationLabel = 'Payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('paid_at')->dateTime('j M Y H:i', config('sudar.business.timezone'))->sortable(),
            TextColumn::make('order.order_number')->searchable()->url(fn (Payment $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
            TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state)),
            TextColumn::make('method')->badge(),
            TextColumn::make('reference')->searchable()->placeholder('—'),
            TextColumn::make('recorder.name')->label('Recorded by'),
        ])->filters([SelectFilter::make('method')->options(PaymentMethod::class)])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayments::route('/')];
    }
}
