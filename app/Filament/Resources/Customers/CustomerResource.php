<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Concerns\RequiresPermission;
use App\Models\Customer;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = Customer::class;

    protected static ?string $permission = 'customers.manage';

    protected static ?string $viewPermission = 'customers.view';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false; // customers are created from orders
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('phone')->disabled()->formatStateUsing(fn ($state) => Phone::display($state))->helperText('Phone is the customer identity and cannot be edited here.'),
                TextInput::make('email')->email(),
                Toggle::make('marketing_opt_in')->label('Marketing consent')->disabled()->helperText('Only the customer can change consent (portal).'),
                Textarea::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('phone')->searchable()->formatStateUsing(fn ($state) => Phone::display($state)),
            TextColumn::make('email')->placeholder('—'),
            TextColumn::make('orders_count')->counts('orders')->label('Orders'),
            IconColumn::make('marketing_opt_in')->label('Marketing')->boolean(),
            TextColumn::make('created_at')->date('j M Y')->sortable(),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\OrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
