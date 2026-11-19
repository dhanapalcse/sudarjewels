<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Domain\Orders\FulfilmentService;
use App\Filament\Support\Guard;
use App\Models\Shipment;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    public function table(Table $table): Table
    {
        $tz = config('sudar.business.timezone');

        return $table->columns([
            TextColumn::make('id')->label('#'),
            TextColumn::make('courier')->placeholder(fn (Shipment $r) => $r->is_handover ? 'In person' : '—'),
            TextColumn::make('tracking_number')->placeholder('—')->url(fn (Shipment $r) => $r->tracking_url, true),
            TextColumn::make('units_count')->counts('units')->label('Pieces'),
            TextColumn::make('dispatched_at')->dateTime('j M Y H:i', $tz),
            TextColumn::make('delivered_at')->dateTime('j M Y H:i', $tz)->placeholder('In transit'),
        ])->recordActions([
            Action::make('delivered')->label('Mark delivered')->icon('heroicon-o-check')->authorize('fulfilment.manage')
                ->visible(fn (Shipment $r) => $r->delivered_at === null)
                ->schema([DateTimePicker::make('at')->label('Delivered at')->default(now())->required()->timezone($tz)])
                ->action(fn (Shipment $r, array $data) => Guard::run(fn () => app(FulfilmentService::class)->markDelivered($r, Carbon::parse($data['at']), auth()->user()), 'Delivered; warranty coverage started')),
        ])->paginated(false);
    }
}
