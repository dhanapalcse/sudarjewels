<?php

namespace App\Models;

use App\Enums\AttributionSource;
use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfilment_status' => FulfilmentStatus::class,
            'channel' => SalesChannel::class,
            'attribution_source' => AttributionSource::class,
            'delivery_required' => 'boolean',
            'attributed_at' => 'datetime',
            'quote_revised_at' => 'datetime',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'analytics_reported_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if ($order->order_number === null) {
                $order->order_number = config('sudar.order_number_prefix').str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(PurchasedUnit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(OrderRevision::class)->orderByDesc('revision');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(OrderChangeRequest::class)->latest();
    }

    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    public function warrantyRegistration(): HasOne
    {
        return $this->hasOne(WarrantyRegistration::class);
    }

    public function balanceDue(): int
    {
        return max(0, $this->grand_total_paise - $this->amount_paid_paise + $this->amount_refunded_paise);
    }

    public function shippingAddressLine(): string
    {
        return collect([$this->address_line1, $this->address_line2, $this->landmark, $this->city, $this->state, $this->postal_code])
            ->filter()->implode(', ');
    }
}
