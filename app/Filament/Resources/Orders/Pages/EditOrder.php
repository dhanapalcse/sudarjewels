<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Orders\OrderService;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Guard;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->record->items->map(fn ($i) => [
            'variant_id' => $i->product_variant_id,
            'quantity' => $i->quantity,
            'unit_price_paise' => $i->unit_price_paise,
            'item_discount_paise' => $i->item_discount_paise,
        ])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return Guard::run(function () use ($record, $data) {
            [$input, $context] = OrderResource::serviceInput($data);

            return app(OrderService::class)->saveQuote($record, $input, auth()->user(), $context);
        });
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->record]);
    }
}
