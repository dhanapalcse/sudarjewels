<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New order')];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'awaiting' => Tab::make('Awaiting confirmation')->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Submitted->value)),
            'to_ship' => Tab::make('To fulfil')->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Confirmed->value)->whereIn('fulfilment_status', ['unfulfilled', 'processing'])),
            'drafts' => Tab::make('Drafts')->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Draft->value)),
        ];
    }
}
