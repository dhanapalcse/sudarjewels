<?php

namespace App\Domain\Warranty;

use App\Domain\Notifications\Notifier;
use App\Enums\UnitStatus;
use App\Enums\WarrantyStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PurchasedUnit;
use App\Models\WarrantyRegistration;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarrantyService
{
    public function __construct(private Notifier $notifier) {}

    /** Called when a unit is delivered/handed over. Coverage is fixed from delivery, not registration. */
    public function applyCoverage(PurchasedUnit $unit): void
    {
        if (! $unit->warranty_eligible || ! $unit->delivered_at || $unit->warranty_starts_at) {
            return;
        }
        [$start, $end] = WarrantyCalculator::coverage($unit->delivered_at, $unit->warranty_months);
        $unit->update(['warranty_starts_at' => $start, 'warranty_ends_at' => $end]);
    }

    /** Units of the order that can be registered now. */
    public function eligibleUnits(Order $order): Collection
    {
        return $order->units()->with('orderItem')
            ->where('warranty_eligible', true)
            ->where('status', UnitStatus::Delivered->value)
            ->whereNotNull('warranty_starts_at')
            ->where('warranty_status', WarrantyStatus::Unregistered->value)
            ->get();
    }

    /**
     * Register an order's warranty. Repeated calls return the existing registration
     * and attach any units that became eligible since (partial deliveries).
     */
    public function register(Order $order, string $registeredName, string $phone, ?Customer $customer = null, string $channel = 'self_service'): WarrantyRegistration
    {
        $registration = DB::transaction(function () use ($order, $registeredName, $phone, $customer, $channel) {
            Order::whereKey($order->id)->lockForUpdate()->first();
            $registration = WarrantyRegistration::where('order_id', $order->id)->first();
            if (! $registration) {
                $registration = WarrantyRegistration::create([
                    'order_id' => $order->id,
                    'customer_id' => $customer?->id ?? $order->customer_id,
                    'registered_name' => trim($registeredName),
                    'registered_phone' => Phone::normalize($phone) ?? $phone,
                    'channel' => $channel,
                    'verified_at' => now(),
                    'registered_at' => now(),
                ]);
            }
            $this->attachUnits($registration, $order);

            return $registration;
        });

        if ($registration->wasRecentlyCreated) {
            DB::afterCommit(fn () => $this->notifier->warrantyRegistered($registration));
        }

        return $registration->load('units.orderItem');
    }

    public function attachNewlyEligibleUnits(Order $order): void
    {
        $registration = WarrantyRegistration::where('order_id', $order->id)->first();
        if ($registration) {
            $this->attachUnits($registration, $order);
        }
    }

    private function attachUnits(WarrantyRegistration $registration, Order $order): void
    {
        $ids = $this->eligibleUnits($order)->pluck('id');
        if ($ids->isNotEmpty()) {
            PurchasedUnit::whereIn('id', $ids)->where('warranty_status', WarrantyStatus::Unregistered->value)->update([
                'warranty_status' => WarrantyStatus::Registered->value,
                'warranty_registration_id' => $registration->id,
                'warranty_registered_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
