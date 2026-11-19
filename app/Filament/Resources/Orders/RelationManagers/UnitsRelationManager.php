<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Domain\Orders\FulfilmentService;
use App\Enums\UnitStatus;
use App\Filament\Support\Guard;
use App\Models\PurchasedUnit;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Per-piece fulfilment: partial dispatch, handover, returns and explicit restock approval. */
class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Pieces & fulfilment';

    public function table(Table $table): Table
    {
        $tz = config('sudar.business.timezone');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('orderItem'))
            ->columns([
                TextColumn::make('code'),
                TextColumn::make('orderItem.product_name')->label('Item')->description(fn (PurchasedUnit $r) => $r->orderItem->variant_name),
                TextColumn::make('status')->badge(),
                TextColumn::make('delivered_at')->dateTime('j M Y', $tz)->placeholder('—'),
                TextColumn::make('warranty_status')->badge(),
                TextColumn::make('warranty_ends_at')->label('Covered until')->dateTime('j M Y', $tz)->placeholder('—'),
                TextColumn::make('restocked_at')->label('Restocked')->dateTime('j M Y', $tz)->placeholder('—'),
                TextColumn::make('replacement_of_unit_id')->label('Replaces')->formatStateUsing(fn ($state) => $state ? PurchasedUnit::find($state)?->code : null)->placeholder('—'),
            ])
            ->toolbarActions([
                BulkAction::make('dispatch')->label('Dispatch selected')->icon('heroicon-o-truck')->authorize('fulfilment.manage')
                    ->schema([
                        TextInput::make('courier')->maxLength(100),
                        TextInput::make('tracking_number')->maxLength(100),
                        TextInput::make('tracking_url')->url()->maxLength(500),
                    ])
                    ->action(fn (Collection $records, array $data) => Guard::run(fn () => app(FulfilmentService::class)->dispatch($this->getOwnerRecord(), $records->pluck('id')->all(), $data, auth()->user()), 'Dispatched')),
                BulkAction::make('handover')->label('Hand over in person')->icon('heroicon-o-hand-raised')->authorize('fulfilment.manage')
                    ->schema([DateTimePicker::make('at')->label('Handed over at')->default(now())->required()->timezone(config('sudar.business.timezone'))])
                    ->action(fn (Collection $records, array $data) => Guard::run(fn () => app(FulfilmentService::class)->handover($this->getOwnerRecord(), $records->pluck('id')->all(), Carbon::parse($data['at']), auth()->user()), 'Handed over; warranty started')),
                BulkAction::make('returned')->label('Mark returned')->icon('heroicon-o-arrow-uturn-left')->color('danger')->authorize('fulfilment.manage')
                    ->schema([Textarea::make('note')->label('Return note')])
                    ->action(fn (Collection $records, array $data) => Guard::run(fn () => app(FulfilmentService::class)->markReturned($this->getOwnerRecord(), $records->pluck('id')->all(), auth()->user(), $data['note'] ?? null), 'Marked returned (stock not added)')),
            ])
            ->recordActions([
                Action::make('restock')->label('Approve restock')->icon('heroicon-o-archive-box-arrow-down')->authorize('refunds.manage')
                    ->visible(fn (PurchasedUnit $r) => $r->returned_at && ! $r->restocked_at)
                    ->requiresConfirmation()->modalDescription('Adds this piece back to sellable stock. Only approve if it is in resaleable condition.')
                    ->action(fn (PurchasedUnit $r) => Guard::run(fn () => app(FulfilmentService::class)->approveRestock($r, auth()->user()), 'Restocked')),
            ])
            ->checkIfRecordIsSelectableUsing(fn (PurchasedUnit $r) => in_array($r->status, [UnitStatus::Allocated, UnitStatus::Dispatched, UnitStatus::Delivered, UnitStatus::Refunded], true))
            ->paginated(false);
    }
}
