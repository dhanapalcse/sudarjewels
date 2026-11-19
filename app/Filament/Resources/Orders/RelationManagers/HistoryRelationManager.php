<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\OrderStatusHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'history';

    protected static ?string $title = 'Status history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->dateTime('j M Y H:i', config('sudar.business.timezone')),
            TextColumn::make('field')->badge(),
            TextColumn::make('change')->state(fn (OrderStatusHistory $r) => trim(($r->from ? $r->from.' → ' : '').($r->to ?? ''))),
            TextColumn::make('note')->wrap()->placeholder('—'),
            TextColumn::make('actor_name')->label('By')->state(fn (OrderStatusHistory $r) => $r->actor?->name ?? 'System'),
        ])->defaultSort('id', 'desc');
    }
}
