<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Orders\OrderService;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Guard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /** One key per form instance: double-clicking "Create" cannot create two orders. */
    public string $idempotencyKey = '';

    public function mount(): void
    {
        parent::mount();
        $this->idempotencyKey = (string) Str::uuid();
    }

    protected function handleRecordCreation(array $data): Model
    {
        return Guard::run(function () use ($data) {
            [$input, $context] = OrderResource::serviceInput($data);
            $service = app(OrderService::class);
            $order = $service->saveQuote(null, $input, auth()->user(), $context + ['status' => OrderStatus::Draft, 'idempotency_key' => $this->idempotencyKey]);
            if (! empty($data['confirm_now'])) {
                $order = $service->confirm($order, auth()->user());
            }

            return $order;
        });
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->record]);
    }
}
