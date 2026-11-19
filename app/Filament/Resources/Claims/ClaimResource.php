<?php

namespace App\Filament\Resources\Claims;

use App\Enums\ClaimStatus;
use App\Filament\Concerns\RequiresPermission;
use App\Models\WarrantyClaim;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ClaimResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = WarrantyClaim::class;

    protected static ?string $permission = 'claims.manage';

    protected static ?string $viewPermission = null;

    protected static ?string $navigationLabel = 'Claims';

    protected static ?string $modelLabel = 'warranty claim';

    protected static ?string $slug = 'claims';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Warranty';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = WarrantyClaim::whereIn('status', ['submitted', 'under_review'])->count();

        return $n ? (string) $n : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('sudar.business.timezone');

        return $schema->components([
            Section::make('Claim')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('claim_number'),
                TextEntry::make('status')->badge(),
                TextEntry::make('created_at')->dateTime('j M Y H:i', $tz),
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('customer.phone')->label('Phone')->formatStateUsing(fn ($state) => Phone::display($state)),
                TextEntry::make('unit.order.order_number')->label('Order'),
                TextEntry::make('unit.orderItem.product_name')->label('Item'),
                TextEntry::make('unit.code')->label('Piece'),
                TextEntry::make('unit.warranty_ends_at')->label('Covered until')->dateTime('j M Y', $tz),
                TextEntry::make('issue_description')->columnSpanFull(),
                TextEntry::make('attachments')->label('Photos (private)')->columnSpanFull()
                    ->state(fn (WarrantyClaim $r) => new HtmlString($r->attachments->map(fn ($a) => '<a class="underline text-primary-600" target="_blank" href="'.e(route('admin.claim-attachment', $a)).'">'.e($a->original_name).'</a>')->implode(' · ') ?: '—')),
            ]),
            Section::make('Decision')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('decision_reason')->placeholder('—'),
                TextEntry::make('resolution')->placeholder('—'),
                TextEntry::make('resolution_notes')->placeholder('—'),
                TextEntry::make('decider.name')->label('Decided by')->placeholder('—'),
                TextEntry::make('replacementUnit.code')->label('Replacement piece')->placeholder('—'),
            ]),
            RepeatableEntry::make('updates')->label('Timeline')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('created_at')->dateTime('j M Y H:i', $tz)->label('When'),
                TextEntry::make('author_type')->label('By')->formatStateUsing(fn ($state) => $state === 'customer' ? 'Customer' : 'Staff'),
                TextEntry::make('status_to')->label('Status')->badge()->placeholder('—'),
                TextEntry::make('message')->formatStateUsing(fn ($state, $record) => ($record->visible_to_customer ? '' : '[internal] ').$state)->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['customer', 'unit.orderItem']))->columns([
            TextColumn::make('claim_number')->searchable(),
            TextColumn::make('customer.name')->searchable(),
            TextColumn::make('unit.orderItem.product_name')->label('Item'),
            TextColumn::make('status')->badge(),
            TextColumn::make('created_at')->since()->sortable(),
        ])->filters([SelectFilter::make('status')->options(ClaimStatus::class)])
            ->recordActions([ViewAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClaims::route('/'),
            'view' => Pages\ViewClaim::route('/{record}'),
        ];
    }
}
