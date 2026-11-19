<?php

namespace App\Filament\Widgets;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Models\CommissionEntry;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\WarrantyClaim;
use App\Models\WarrantyRegistration;
use App\Support\Money;
use App\Support\Settings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BusinessOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    protected function getStats(): array
    {
        $since = now()->subDays(30);
        $sales = (int) Order::whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Completed->value])->where('confirmed_at', '>=', $since)->sum('grand_total_paise');
        $refunds = (int) Refund::where('refunded_at', '>=', $since)->sum('amount_paise');
        $low = (int) app(Settings::class)->get('inventory.low_stock_threshold');

        return [
            Stat::make('Sales (30 days)', Money::format($sales - $refunds))->description('Confirmed orders, net of refunds'),
            Stat::make('Orders awaiting confirmation', Order::where('status', OrderStatus::Submitted->value)->count())
                ->description(Order::where('status', OrderStatus::Confirmed->value)->whereIn('fulfilment_status', ['unfulfilled', 'processing'])->count().' confirmed orders to fulfil'),
            Stat::make('Low stock variants', ProductVariant::where('is_active', true)->whereRaw('stock_on_hand - stock_reserved <= ?', [$low])->count())->description("Available ≤ {$low}")->color('danger'),
            Stat::make('Pending commission', Money::format((int) CommissionEntry::where('status', CommissionStatus::Pending->value)->sum('amount_paise'))),
            Stat::make('Payable commission', Money::format((int) CommissionEntry::where('status', CommissionStatus::Payable->value)->whereNull('payout_id')->sum('amount_paise')))
                ->description(app(Settings::class)->get('commission.return_window_days') === null ? 'Set the return window in Business settings' : 'Ready for payout'),
            Stat::make('Warranty registrations (30 days)', WarrantyRegistration::where('registered_at', '>=', $since)->count()),
            Stat::make('Open claims', WarrantyClaim::whereNotIn('status', ['rejected', 'resolved'])->count()),
        ];
    }
}
