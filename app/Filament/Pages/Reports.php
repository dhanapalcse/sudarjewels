<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Models\CommissionEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\WarrantyClaim;
use App\Models\WarrantyRegistration;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.reports';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && $u->isStaff() && $u->can('reports.view');
    }

    public function mount(): void
    {
        $tz = config('sudar.business.timezone');
        $this->from = $this->from ?: now($tz)->startOfMonth()->toDateString();
        $this->to = $this->to ?: now($tz)->toDateString();
    }

    /** @return array{0: Carbon, 1: Carbon} UTC bounds of the selected local-date range */
    private function range(): array
    {
        $tz = config('sudar.business.timezone');
        $from = rescue(fn () => Carbon::parse($this->from, $tz)->startOfDay(), now($tz)->startOfMonth(), false);
        $to = rescue(fn () => Carbon::parse($this->to, $tz)->endOfDay(), now($tz)->endOfDay(), false);

        return [$from->utc(), $to->utc()];
    }

    private function orders()
    {
        [$from, $to] = $this->range();

        return Order::whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Completed->value])->whereBetween('confirmed_at', [$from, $to]);
    }

    protected function getViewData(): array
    {
        [$from, $to] = $this->range();
        $orders = $this->orders();
        $gross = (int) (clone $orders)->sum('grand_total_paise');
        $count = (clone $orders)->count();
        $refunds = (int) Refund::whereBetween('refunded_at', [$from, $to])->sum('amount_paise');

        return [
            'summary' => [
                'Orders' => $count,
                'Gross sales' => Money::format($gross),
                'Refunds' => Money::format($refunds),
                'Net sales' => Money::format($gross - $refunds),
                'Average order' => Money::format($count ? intdiv($gross, $count) : 0),
                'Warranty registrations' => WarrantyRegistration::whereBetween('registered_at', [$from, $to])->count(),
                'Claims opened' => WarrantyClaim::whereBetween('created_at', [$from, $to])->count(),
            ],
            'byChannel' => (clone $orders)->select('channel', DB::raw('count(*) as orders'), DB::raw('sum(grand_total_paise) as total'))->groupBy('channel')->get(),
            'topProducts' => OrderItem::whereIn('order_id', (clone $orders)->select('id'))
                ->select('sku', DB::raw('max(product_name) as name'), DB::raw('sum(quantity) as qty'), DB::raw('sum(line_total_paise) as total'))
                ->groupBy('sku')->orderByDesc('qty')->limit(10)->get(),
            'affiliates' => CommissionEntry::with('affiliate.user')->whereBetween('created_at', [$from, $to])
                ->select('affiliate_id', DB::raw("sum(case when type = 'earning' then amount_paise else 0 end) as earned"), DB::raw("sum(case when type <> 'earning' then amount_paise else 0 end) as adjustments"), DB::raw('count(distinct order_id) as orders'))
                ->groupBy('affiliate_id')->orderByDesc('earned')->get(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export orders (CSV)')->icon('heroicon-o-arrow-down-tray')->authorize('reports.view')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $orders = $this->orders()->with('affiliate')->orderBy('id');
        $tz = config('sudar.business.timezone');

        return response()->streamDownload(function () use ($orders, $tz) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Confirmed (IST)', 'Channel', 'Status', 'Payment', 'Fulfilment', 'Subtotal', 'Discounts', 'Tax', 'Shipping', 'Total', 'Paid', 'Refunded', 'Affiliate', 'Commission estimate']);
            $orders->chunk(500, function ($chunk) use ($out, $tz) {
                foreach ($chunk as $o) {
                    fputcsv($out, [$o->order_number, $o->confirmed_at?->timezone($tz)->format('Y-m-d H:i'), $o->channel->value, $o->status->value, $o->payment_status->value, $o->fulfilment_status->value,
                        $o->subtotal_paise / 100, $o->discount_total_paise / 100, $o->tax_paise / 100, $o->shipping_paise / 100, $o->grand_total_paise / 100, $o->amount_paid_paise / 100, $o->amount_refunded_paise / 100,
                        $o->affiliate?->code, $o->commission_estimate_paise / 100]);
                }
            });
            fclose($out);
        }, 'sudar-orders-'.$this->from.'-to-'.$this->to.'.csv', ['Content-Type' => 'text/csv']);
    }
}
