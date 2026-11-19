<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Orders\OrderService;
use App\Domain\Orders\PaymentService;
use App\Domain\Warranty\WarrantyService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Guard;
use App\Filament\Support\MoneyInput;
use App\Models\Affiliate;
use App\Models\Order;
use App\Support\Audit;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    private function order(): Order
    {
        return $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        $o = fn () => $this->record;

        return [
            Action::make('confirm')->label('Confirm order')->icon('heroicon-o-check-badge')->color('primary')
                ->authorize('orders.manage')
                ->visible(fn () => $o()->status->isEditableQuote())
                ->requiresConfirmation()->modalDescription('Revalidates prices and stock, reserves stock and creates pending commission.')
                ->action(fn () => Guard::run(fn () => app(OrderService::class)->confirm($this->order(), auth()->user()), 'Order confirmed')),

            Action::make('reviseQuote')->label('Apply current prices (revise quote)')->icon('heroicon-o-arrow-path')->color('warning')
                ->authorize('orders.manage')
                ->visible(fn () => $o()->status->isEditableQuote() && $o()->channel === SalesChannel::Affiliate)
                ->schema([Textarea::make('reason')->required()->maxLength(500)->default('Prices or commission rates changed since the quote.')])
                ->action(function (array $data) {
                    $differences = app(OrderService::class)->quoteDifferences($this->order());
                    Guard::run(fn () => app(OrderService::class)->reviseQuote($this->order(), auth()->user(), $data['reason']), $differences ? 'Quote revised — the affiliate has been notified' : 'Quote already matches current prices');
                }),

            EditAction::make()->visible(fn () => $o()->status->isEditableQuote()),

            ActionGroup::make([
                Action::make('submit')->label('Mark submitted')->visible(fn () => $o()->status === OrderStatus::Draft)->authorize('orders.manage')
                    ->action(fn () => Guard::run(fn () => app(OrderService::class)->submit($this->order(), auth()->user()), 'Order submitted')),
                Action::make('processing')->label(fn () => $o()->fulfilment_status->value === 'processing' ? 'Back to unfulfilled' : 'Mark packing / processing')
                    ->visible(fn () => $o()->status === OrderStatus::Confirmed && in_array($o()->fulfilment_status->value, ['unfulfilled', 'processing'], true))
                    ->authorize('fulfilment.manage')
                    ->action(fn () => Guard::run(fn () => app(OrderService::class)->setProcessing($this->order(), auth()->user(), $this->record->fulfilment_status->value !== 'processing'), 'Fulfilment status updated')),
                Action::make('complete')->label('Mark completed')->visible(fn () => $o()->status === OrderStatus::Confirmed)->authorize('orders.manage')
                    ->requiresConfirmation()
                    ->action(fn () => Guard::run(fn () => app(OrderService::class)->complete($this->order(), auth()->user()), 'Order completed')),
                Action::make('attribution')->label('Change affiliate attribution')->icon('heroicon-o-user-plus')->authorize('commissions.manage')
                    ->visible(fn () => ! in_array($o()->status, [OrderStatus::Cancelled, OrderStatus::Completed], true))
                    ->schema([
                        Select::make('affiliate_id')->label('Affiliate (empty = none)')
                            ->options(fn () => Affiliate::with('user')->where('status', 'active')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} — {$a->name()}"]))->searchable(),
                        Textarea::make('reason')->required()->maxLength(500),
                    ])
                    ->action(fn (array $data) => Guard::run(fn () => app(OrderService::class)->changeAttribution($this->order(), $data['affiliate_id'] ? Affiliate::find($data['affiliate_id']) : null, auth()->user(), $data['reason']), 'Attribution updated (audited)')),
                Action::make('registerWarranty')->label('Register warranty for customer')->icon('heroicon-o-shield-check')->authorize('warranty.manage')
                    ->visible(fn () => in_array($o()->status, [OrderStatus::Confirmed, OrderStatus::Completed], true))
                    ->schema([
                        TextInput::make('name')->label('Registered name')->default(fn () => $o()->customer_name)->required(),
                        Textarea::make('reason')->label('How was the customer verified?')->required(),
                    ])
                    ->action(function (array $data) {
                        $reg = Guard::run(fn () => app(WarrantyService::class)->register($this->order(), $data['name'], $this->record->customer_phone, $this->record->customer, 'admin'), 'Warranty registered');
                        Audit::log('warranty.admin_registered', $reg, ['order' => $this->record->order_number], $data['reason']);
                    }),
                Action::make('cancel')->label('Cancel order')->color('danger')->icon('heroicon-o-x-circle')->authorize('orders.manage')
                    ->visible(fn () => in_array($o()->status, [OrderStatus::Draft, OrderStatus::Submitted, OrderStatus::Confirmed], true))
                    ->schema([Textarea::make('reason')->required()->maxLength(500)])
                    ->requiresConfirmation()
                    ->action(fn (array $data) => Guard::run(fn () => app(OrderService::class)->cancel($this->order(), auth()->user(), $data['reason']), 'Order cancelled')),
            ])->label('More')->button()->color('gray'),

            Action::make('payment')->label('Record payment')->icon('heroicon-o-banknotes')->authorize('payments.manage')
                ->visible(fn () => in_array($o()->status, [OrderStatus::Submitted, OrderStatus::Confirmed, OrderStatus::Completed], true) && $o()->amount_paid_paise < $o()->grand_total_paise)
                ->schema([
                    MoneyInput::make('amount', 'Amount')->required()->default(fn () => Money::toRupees($o()->grand_total_paise - $o()->amount_paid_paise)),
                    Select::make('method')->options(PaymentMethod::class)->required()->default(PaymentMethod::Upi),
                    TextInput::make('reference')->label('Reference / UTR')->maxLength(190),
                    DateTimePicker::make('paid_at')->default(now())->required()->timezone(config('sudar.business.timezone')),
                    Textarea::make('notes')->rows(2),
                    Hidden::make('key')->default(fn () => (string) Str::uuid()),
                ])
                ->action(fn (array $data) => Guard::run(fn () => app(PaymentService::class)->recordPayment(
                    $this->order(), (int) $data['amount'], PaymentMethod::from($data['method'] instanceof PaymentMethod ? $data['method']->value : $data['method']),
                    $data['reference'] ?? null, Carbon::parse($data['paid_at']), auth()->user(), 'pay:'.$data['key'], $data['notes'] ?? null,
                ), 'Payment recorded')),

            Action::make('refund')->label('Refund')->icon('heroicon-o-receipt-refund')->color('danger')->authorize('refunds.manage')
                ->visible(fn () => $o()->amount_paid_paise > $o()->amount_refunded_paise)
                ->modalWidth('3xl')
                ->schema([
                    Repeater::make('lines')->label('Refunded items (optional — leave empty for a shipping or goodwill refund)')->defaultItems(0)->columns(3)->schema([
                        Select::make('order_item_id')->label('Item')->required()->options(fn () => $o()->items->mapWithKeys(fn ($i) => [$i->id => $i->label().' ('.$i->sku.') — '.Money::format($i->line_total_paise)])),
                        TextInput::make('quantity')->label('Units')->integer()->minValue(0)->default(1)->required()->helperText('0 = partial amount without returning units'),
                        MoneyInput::make('amount_paise', 'Amount for this item')->required(),
                    ]),
                    MoneyInput::make('amount', 'Total refund')->required(),
                    Select::make('method')->options(PaymentMethod::class)->required()->default(PaymentMethod::Upi),
                    TextInput::make('reference')->maxLength(190),
                    Textarea::make('reason')->required()->maxLength(500),
                    Hidden::make('key')->default(fn () => (string) Str::uuid()),
                ])
                ->action(fn (array $data) => Guard::run(fn () => app(PaymentService::class)->recordRefund(
                    $this->order(), (int) $data['amount'],
                    array_map(fn ($l) => ['order_item_id' => (int) $l['order_item_id'], 'quantity' => (int) $l['quantity'], 'amount_paise' => (int) $l['amount_paise']], $data['lines'] ?? []),
                    PaymentMethod::from($data['method'] instanceof PaymentMethod ? $data['method']->value : $data['method']),
                    $data['reference'] ?? null, $data['reason'], auth()->user(), 'refund:'.$data['key'],
                ), 'Refund recorded; commission adjusted')),
        ];
    }
}
