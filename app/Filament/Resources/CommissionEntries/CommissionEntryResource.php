<?php

namespace App\Filament\Resources\CommissionEntries;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Filament\Concerns\RequiresPermission;
use App\Models\CommissionEntry;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Append-only ledger: entries are never edited; corrections are new entries. */
class CommissionEntryResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = CommissionEntry::class;

    protected static ?string $permission = null;

    protected static ?string $viewPermission = 'commissions.manage';

    protected static ?string $navigationLabel = 'Commissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyRupee;

    protected static string|UnitEnum|null $navigationGroup = 'Affiliates';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['affiliate', 'order', 'payout']))
            ->columns([
                TextColumn::make('created_at')->dateTime('j M Y', config('sudar.business.timezone'))->sortable(),
                TextColumn::make('affiliate.code')->label('Affiliate')->searchable(),
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('description')->limit(40)->tooltip(fn (CommissionEntry $r) => $r->description),
                TextColumn::make('rate_bp')->label('Rate')->formatStateUsing(fn ($state) => Money::formatRate($state))->placeholder('—'),
                TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state))->color(fn ($state) => $state < 0 ? 'danger' : null),
                TextColumn::make('payout_id')->label('Payout')->formatStateUsing(fn ($state) => $state ? '#'.$state : null)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(CommissionStatus::class),
                SelectFilter::make('type')->options(CommissionType::class),
                SelectFilter::make('affiliate_id')->label('Affiliate')->relationship('affiliate', 'code'),
                TernaryFilter::make('in_payout')->label('In a payout')->queries(true: fn ($query) => $query->whereNotNull('payout_id'), false: fn ($query) => $query->whereNull('payout_id')),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCommissionEntries::route('/')];
    }
}
