<?php

namespace App\Filament\Resources\Payouts;

use App\Enums\PayoutStatus;
use App\Filament\Concerns\RequiresPermission;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\CommissionEntry;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PayoutResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = AffiliatePayout::class;

    protected static ?string $permission = 'payouts.manage';

    protected static ?string $viewPermission = null;

    protected static ?string $navigationLabel = 'Payouts';

    protected static ?string $modelLabel = 'payout';

    protected static ?string $slug = 'payouts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Affiliates';

    protected static ?int $navigationSort = 3;

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function payableEntries(?int $affiliateId)
    {
        return $affiliateId ? CommissionEntry::with('order')->where('affiliate_id', $affiliateId)->where('status', 'payable')->whereNull('payout_id')->orderBy('id')->get() : collect();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('affiliate_id')->label('Affiliate')->required()->live()->searchable()
                    ->options(fn () => Affiliate::with('user')->whereHas('commissionEntries', fn ($query) => $query->where('status', 'payable')->whereNull('payout_id'))
                        ->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} — {$a->name()} · payable ".Money::format((int) $a->commissionEntries()->where('status', 'payable')->whereNull('payout_id')->sum('amount_paise'))])),
                Text::make(fn (Get $get) => ($a = Affiliate::find($get('affiliate_id'))) ? 'Pay to: '.(AffiliateResourceProxy::canSee() ? ($a->maskedPayoutDestination() ?? 'No payout details on file') : 'hidden (no permission)') : '')
                    ->visible(fn (Get $get) => filled($get('affiliate_id'))),
                CheckboxList::make('entry_ids')->label('Commission entries to include')->required()->live()->bulkToggleable()
                    ->options(fn (Get $get) => static::payableEntries($get('affiliate_id'))->mapWithKeys(fn ($e) => [$e->id => ($e->order?->order_number ?? '—').' · '.$e->type->label().' · '.Money::format($e->amount_paise).' · '.$e->description]))
                    ->visible(fn (Get $get) => filled($get('affiliate_id'))),
                Text::make(fn (Get $get) => 'Selected total: '.Money::format((int) CommissionEntry::whereIn('id', (array) $get('entry_ids'))->sum('amount_paise')))
                    ->visible(fn (Get $get) => filled($get('entry_ids'))),
                Textarea::make('notes'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('sudar.business.timezone');

        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('affiliate.code')->label('Affiliate'),
                TextEntry::make('status')->badge(),
                TextEntry::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state))->weight('bold'),
                TextEntry::make('destination_snapshot')->label('Destination (masked)')->visible(fn () => AffiliateResourceProxy::canSee())->placeholder('—'),
                TextEntry::make('method')->placeholder('—'),
                TextEntry::make('reference')->placeholder('—'),
                TextEntry::make('paid_on')->date('j M Y')->placeholder('—'),
                TextEntry::make('creator.name')->label('Created by'),
                TextEntry::make('created_at')->dateTime('j M Y H:i', $tz),
                TextEntry::make('failure_reason')->placeholder('—'),
                TextEntry::make('notes')->placeholder('—'),
            ]),
            RepeatableEntry::make('items')->label('Entries')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('entry.order.order_number')->label('Order'),
                TextEntry::make('entry.type')->label('Type')->badge(),
                TextEntry::make('entry.description')->label('Description'),
                TextEntry::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('#'),
            TextColumn::make('affiliate.code')->label('Affiliate')->searchable(),
            TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => Money::format($state)),
            TextColumn::make('status')->badge(),
            TextColumn::make('method')->placeholder('—'),
            TextColumn::make('reference')->placeholder('—'),
            TextColumn::make('created_at')->date('j M Y')->sortable(),
        ])->filters([SelectFilter::make('status')->options(PayoutStatus::class)])
            ->recordActions([ViewAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
            'create' => Pages\CreatePayout::route('/create'),
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }
}
