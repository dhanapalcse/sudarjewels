<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Filament\Resources\Payouts\PayoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New payout')];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'processing' => Tab::make('Processing')->modifyQueryUsing(fn ($query) => $query->where('status', 'processing')),
            'completed' => Tab::make('Completed')->modifyQueryUsing(fn ($query) => $query->where('status', 'completed')),
            'failed' => Tab::make('Failed / cancelled')->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['failed', 'cancelled'])),
        ];
    }
}
