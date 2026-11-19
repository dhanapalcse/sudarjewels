<?php

namespace App\Filament\Resources\Affiliates;

use App\Domain\Commissions\CommissionService;
use App\Enums\AffiliateStatus;
use App\Enums\PayoutMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Models\Affiliate;
use App\Support\Audit;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AffiliateResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = Affiliate::class;

    protected static ?string $permission = 'affiliates.manage';

    protected static ?string $viewPermission = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Affiliates';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $n = Affiliate::where('status', AffiliateStatus::Pending->value)->count();

        return $n ? (string) $n : null;
    }

    public static function canSeePayoutDetails(): bool
    {
        return (bool) auth()->user()?->can('affiliates.payout_details');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('user_name')->label('Name')->required()->maxLength(120),
                TextInput::make('user_email')->label('Login email')->email()->required(),
                TextInput::make('password')->password()->revealable()->visibleOn('create')->required()->minLength(10)->helperText('Share securely; the affiliate can change it later.'),
                TextInput::make('code')->required()->unique(ignoreRecord: true)->regex('/^[A-Z0-9]{3,32}$/')->helperText('Uppercase letters and numbers, e.g. SUDAR101'),
                TextInput::make('phone')->tel(),
                TextInput::make('city'),
                TextInput::make('social_handle'),
                Textarea::make('admin_notes')->columnSpanFull(),
            ]),
            Section::make('Payout details (private)')->columns(2)->visible(fn () => static::canSeePayoutDetails())->schema([
                Select::make('payout_method')->options(PayoutMethod::class),
                TextInput::make('payout_upi_id')->label('UPI ID'),
                TextInput::make('payout_account_name')->label('Account name'),
                TextInput::make('payout_account_number')->label('Account number'),
                TextInput::make('payout_ifsc')->label('IFSC'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $balances = app(CommissionService::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('orders'))
            ->columns([
                TextColumn::make('code')->searchable()->weight('bold'),
                TextColumn::make('user.name')->label('Name')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable()->toggleable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('orders_count')->label('Orders'),
                TextColumn::make('pending')->state(fn (Affiliate $a) => Money::format($balances->balances($a->id)['pending'])),
                TextColumn::make('payable')->state(fn (Affiliate $a) => Money::format($balances->balances($a->id)['payable'])),
                TextColumn::make('payout')->label('Payout to')->state(fn (Affiliate $a) => static::canSeePayoutDetails() ? $a->maskedPayoutDestination() : ($a->payout_method ? 'Set' : null))->placeholder('Not set'),
            ])
            ->filters([SelectFilter::make('status')->options(AffiliateStatus::class)])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    static::statusAction('approve', 'Approve & activate', AffiliateStatus::Active, fn (Affiliate $a) => in_array($a->status, [AffiliateStatus::Pending, AffiliateStatus::Rejected], true)),
                    static::statusAction('suspend', 'Suspend', AffiliateStatus::Suspended, fn (Affiliate $a) => $a->status === AffiliateStatus::Active),
                    static::statusAction('reactivate', 'Reactivate', AffiliateStatus::Active, fn (Affiliate $a) => $a->status === AffiliateStatus::Suspended),
                    static::statusAction('reject', 'Reject', AffiliateStatus::Rejected, fn (Affiliate $a) => $a->status === AffiliateStatus::Pending),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    private static function statusAction(string $name, string $label, AffiliateStatus $to, \Closure $visible): Action
    {
        return Action::make($name)->label($label)->authorize('affiliates.manage')->visible($visible)
            ->schema([Textarea::make('reason')->label('Note (optional)')])
            ->action(function (Affiliate $a, array $data) use ($to) {
                $from = $a->status;
                $a->update(['status' => $to] + ($to === AffiliateStatus::Active && ! $a->approved_at ? ['approved_at' => now(), 'approved_by_id' => auth()->id()] : []));
                Audit::log('affiliate.status_changed', $a, ['from' => $from->value, 'to' => $to->value], $data['reason'] ?? null);
            });
    }

    public static function getRelations(): array
    {
        return [RelationManagers\CommissionEntriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliates::route('/'),
            'create' => Pages\CreateAffiliate::route('/create'),
            'edit' => Pages\EditAffiliate::route('/{record}/edit'),
        ];
    }
}
