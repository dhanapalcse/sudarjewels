<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Domain\Commissions\PayoutService;
use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Filament\Resources\Payouts\PayoutResource;
use App\Filament\Support\Guard;
use App\Filament\Support\MoneyInput;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

class ViewPayout extends ViewRecord
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        $processing = fn () => $this->record->status === PayoutStatus::Processing;

        return [
            Action::make('complete')->label('Record payment')->icon('heroicon-o-check')->authorize('payouts.manage')->visible($processing)
                ->schema([
                    Select::make('method')->options(PayoutMethod::class)->required()->default(fn () => $this->record->method),
                    TextInput::make('reference')->label('UTR / transaction reference')->required()->maxLength(190),
                    DatePicker::make('paid_on')->default(now())->required(),
                    MoneyInput::make('amount', 'Amount paid')->required()->default(fn () => Money::toRupees($this->record->amount_paise))->helperText('Must equal the payout total.'),
                ])
                ->action(fn (array $data) => Guard::run(fn () => app(PayoutService::class)->complete(
                    $this->record, PayoutMethod::from($data['method'] instanceof PayoutMethod ? $data['method']->value : $data['method']),
                    $data['reference'], Carbon::parse($data['paid_on']), (int) $data['amount'], auth()->user(),
                ), 'Payout completed; entries marked paid')),
            Action::make('fail')->label('Mark failed')->color('danger')->authorize('payouts.manage')->visible($processing)
                ->schema([Textarea::make('reason')->required()])
                ->action(fn (array $data) => Guard::run(fn () => app(PayoutService::class)->abandon($this->record, PayoutStatus::Failed, $data['reason'], auth()->user()), 'Payout marked failed; entries released')),
            Action::make('cancel')->label('Cancel payout')->color('gray')->authorize('payouts.manage')->visible($processing)
                ->schema([Textarea::make('reason')->required()])
                ->action(fn (array $data) => Guard::run(fn () => app(PayoutService::class)->abandon($this->record, PayoutStatus::Cancelled, $data['reason'], auth()->user()), 'Payout cancelled; entries released')),
        ];
    }
}
