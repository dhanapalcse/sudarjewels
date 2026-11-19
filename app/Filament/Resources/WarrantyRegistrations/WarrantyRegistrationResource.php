<?php

namespace App\Filament\Resources\WarrantyRegistrations;

use App\Filament\Concerns\RequiresPermission;
use App\Models\WarrantyRegistration;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WarrantyRegistrationResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = WarrantyRegistration::class;

    protected static ?string $permission = 'warranty.manage';

    protected static ?string $viewPermission = null;

    protected static ?string $navigationLabel = 'Registrations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Warranty';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // admin-assisted registration is done from the order page
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('sudar.business.timezone');

        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('order.order_number')->label('Order'),
                TextEntry::make('registered_name')->helperText('As entered by the customer'),
                TextEntry::make('order.customer_name')->label('Name on order'),
                TextEntry::make('registered_phone')->formatStateUsing(fn ($state) => Phone::display($state)),
                TextEntry::make('channel')->badge(),
                TextEntry::make('registered_at')->dateTime('j M Y H:i', $tz),
                TextEntry::make('uuid')->label('Card ID')->copyable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order.order_number')->label('Order')->searchable(),
            TextColumn::make('registered_name')->searchable(),
            TextColumn::make('registered_phone')->formatStateUsing(fn ($state) => Phone::display($state))->searchable(),
            TextColumn::make('units_count')->counts('units')->label('Pieces'),
            TextColumn::make('channel')->badge(),
            TextColumn::make('registered_at')->dateTime('j M Y', config('sudar.business.timezone'))->sortable(),
        ])->recordActions([ViewAction::make()])->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\UnitsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarrantyRegistrations::route('/'),
            'view' => Pages\ViewWarrantyRegistration::route('/{record}'),
        ];
    }
}
