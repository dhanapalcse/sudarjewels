<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Affiliates\ReferralService;
use App\Enums\AttributionSource;
use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Exceptions\BusinessRuleException;
use App\Filament\Support\MoneyInput;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'order_number';

    // Authorisation comes from App\Policies\OrderPolicy.
    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && $record->status->isEditableQuote();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Order::where('status', OrderStatus::Submitted->value)->count();

        return $n ? (string) $n : null;
    }

    public static function variantLabel(ProductVariant $v): string
    {
        return "{$v->product->name} — {$v->displayName()} ({$v->sku}) · ".Money::format($v->effectivePrice())." · {$v->available()} available";
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer')->columns(2)->schema([
                TextInput::make('customer_name')->required()->maxLength(120),
                TextInput::make('customer_phone')->required()->tel()->rule(fn () => fn ($a, $v, $fail) => Phone::isValid($v) ? null : $fail('Enter a valid Indian mobile number.')),
                TextInput::make('customer_email')->email(),
                Select::make('channel')->options(SalesChannel::class)->default(SalesChannel::WhatsApp)->required(),
            ]),
            Section::make('Delivery')->columns(2)->schema([
                Toggle::make('delivery_required')->default(true)->live()->columnSpanFull()->helperText('Turn off for in-person handover.'),
                TextInput::make('shipping_name')->label('Recipient (if different)')->visible(fn (Get $get) => $get('delivery_required')),
                TextInput::make('shipping_phone')->label('Recipient phone')->visible(fn (Get $get) => $get('delivery_required')),
                TextInput::make('address_line1')->required(fn (Get $get) => $get('delivery_required'))->visible(fn (Get $get) => $get('delivery_required')),
                TextInput::make('address_line2')->visible(fn (Get $get) => $get('delivery_required')),
                TextInput::make('landmark')->visible(fn (Get $get) => $get('delivery_required')),
                TextInput::make('city')->required(fn (Get $get) => $get('delivery_required'))->visible(fn (Get $get) => $get('delivery_required')),
                TextInput::make('state')->required(fn (Get $get) => $get('delivery_required'))->visible(fn (Get $get) => $get('delivery_required')),
                TextInput::make('postal_code')->label('PIN code')->regex('/^[1-9][0-9]{5}$/')->required(fn (Get $get) => $get('delivery_required'))->visible(fn (Get $get) => $get('delivery_required')),
            ]),
            Section::make('Items')->schema([
                Repeater::make('lines')->minItems(1)->required()->defaultItems(1)->columns(4)->schema([
                    Select::make('variant_id')->label('Product / variant')->required()->searchable()->columnSpan(2)
                        ->getSearchResultsUsing(fn (string $search) => ProductVariant::with('product')->where('is_active', true)
                            ->where(fn ($query) => $query->where('sku', 'like', "%{$search}%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")))
                            ->limit(30)->get()->mapWithKeys(fn ($v) => [$v->id => static::variantLabel($v)]))
                        ->getOptionLabelUsing(fn ($value) => ($v = ProductVariant::with('product')->find($value)) ? static::variantLabel($v) : null),
                    TextInput::make('quantity')->integer()->minValue(1)->maxValue(100)->default(1)->required(),
                    MoneyInput::make('unit_price_paise', 'Unit price override', nullable: true)->helperText('Blank = catalogue price'),
                    MoneyInput::make('item_discount_paise', 'Item discount'),
                ]),
            ]),
            Section::make('Pricing & attribution')->columns(2)->schema([
                MoneyInput::make('order_discount_paise', 'Order discount')->helperText('Allocated across items in proportion to their value.'),
                MoneyInput::make('shipping_paise', 'Shipping', nullable: true)->helperText('Blank = default shipping rule'),
                Select::make('affiliate_id')->label('Affiliate (optional)')
                    ->options(fn () => Affiliate::with('user')->where('status', 'active')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} — {$a->name()}"]))
                    ->searchable()->helperText('One affiliate per order. Only active affiliates are listed.'),
                TextInput::make('referral_code')->label('…or referral code given by customer')->maxLength(32),
            ]),
            Section::make('Notes')->columns(2)->schema([
                Textarea::make('customer_notes')->rows(2),
                Textarea::make('internal_notes')->rows(2),
                Toggle::make('confirm_now')->label('Confirm immediately (reserves stock)')->default(false)->visibleOn('create')->dehydrated(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('sudar.business.timezone');
        $m = fn ($state) => Money::format((int) $state);

        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                Section::make('Status')->columns(2)->schema([
                    TextEntry::make('status')->badge(),
                    TextEntry::make('payment_status')->badge(),
                    TextEntry::make('fulfilment_status')->badge(),
                    TextEntry::make('channel')->badge(),
                    TextEntry::make('creator.name')->label('Created by')->placeholder('—'),
                    TextEntry::make('created_at')->dateTime('j M Y H:i', $tz),
                    TextEntry::make('confirmed_at')->dateTime('j M Y H:i', $tz)->placeholder('—'),
                    TextEntry::make('quote_revision')->label('Quote revision'),
                ]),
                Section::make('Customer')->schema([
                    TextEntry::make('customer_name'),
                    TextEntry::make('customer_phone')->formatStateUsing(fn ($state) => Phone::display($state)),
                    TextEntry::make('customer_email')->placeholder('—'),
                    TextEntry::make('address')->state(fn (Order $r) => $r->delivery_required ? $r->shippingAddressLine() : 'No delivery (in person)'),
                ]),
                Section::make('Totals')->columns(2)->schema([
                    TextEntry::make('subtotal_paise')->label('Subtotal')->formatStateUsing($m),
                    TextEntry::make('discount_total_paise')->label('Discounts')->formatStateUsing($m),
                    TextEntry::make('tax_paise')->label('Tax (incl.)')->formatStateUsing($m),
                    TextEntry::make('shipping_paise')->label('Shipping')->formatStateUsing($m),
                    TextEntry::make('grand_total_paise')->label('Total')->formatStateUsing($m)->weight('bold'),
                    TextEntry::make('amount_paid_paise')->label('Paid')->formatStateUsing($m),
                    TextEntry::make('amount_refunded_paise')->label('Refunded')->formatStateUsing($m),
                    TextEntry::make('balance')->state(fn (Order $r) => $r->balanceDue())->formatStateUsing($m)->label('Balance due'),
                ]),
            ]),
            Section::make('Attribution')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('affiliate.code')->label('Affiliate')->placeholder('None'),
                TextEntry::make('attribution_source')->badge()->placeholder('—'),
                TextEntry::make('referral_code')->placeholder('—'),
                TextEntry::make('commission_estimate_paise')->label('Commission (estimate)')->formatStateUsing($m),
            ]),
            Section::make('Notes')->columns(2)->columnSpanFull()->collapsed()->schema([
                TextEntry::make('customer_notes')->placeholder('—'),
                TextEntry::make('internal_notes')->placeholder('—'),
                TextEntry::make('cancellation_reason')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('affiliate'))
            ->columns([
                TextColumn::make('order_number')->searchable()->sortable()->weight('bold'),
                TextColumn::make('customer_name')->searchable()->description(fn (Order $r) => Phone::display($r->customer_phone)),
                TextColumn::make('channel')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_status')->badge(),
                TextColumn::make('fulfilment_status')->badge(),
                TextColumn::make('grand_total_paise')->label('Total')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('affiliate.code')->label('Affiliate')->placeholder('—'),
                TextColumn::make('created_at')->dateTime('j M Y', config('sudar.business.timezone'))->sortable(),
            ])
            ->searchable()
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class),
                SelectFilter::make('payment_status')->options(PaymentStatus::class),
                SelectFilter::make('fulfilment_status')->options(FulfilmentStatus::class),
                SelectFilter::make('channel')->options(SalesChannel::class),
                SelectFilter::make('affiliate_id')->label('Affiliate')->relationship('affiliate', 'code'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\UnitsRelationManager::class,
            RelationManagers\ShipmentsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\RefundsRelationManager::class,
            RelationManagers\ChangeRequestsRelationManager::class,
            RelationManagers\HistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    /** Convert form state into OrderService input + context. */
    public static function serviceInput(array $data): array
    {
        $affiliate = null;
        $source = null;
        if (! empty($data['affiliate_id'])) {
            $affiliate = Affiliate::with('user')->find($data['affiliate_id']);
            $source = AttributionSource::AdminAssigned;
        } elseif (filled($data['referral_code'] ?? null)) {
            $affiliate = app(ReferralService::class)->findActive($data['referral_code'])
                ?? throw new BusinessRuleException('That referral code is not valid or not active.');
            $source = AttributionSource::ExplicitCode;
        }
        if ($affiliate && ! $affiliate->isActive()) {
            throw new BusinessRuleException('The selected affiliate is not active.');
        }
        $data['lines'] = array_values(array_map(fn ($l) => [
            'variant_id' => (int) $l['variant_id'],
            'quantity' => (int) $l['quantity'],
            'unit_price_paise' => $l['unit_price_paise'] ?? null,
            'item_discount_paise' => (int) ($l['item_discount_paise'] ?? 0),
        ], $data['lines'] ?? []));
        $channel = $data['channel'] instanceof SalesChannel ? $data['channel']->value : $data['channel'];

        return [
            $data + ['channel' => $channel],
            ['allow_overrides' => true, 'affiliate' => $affiliate, 'attribution_source' => $source, 'referral_code' => $affiliate?->code],
        ];
    }
}
