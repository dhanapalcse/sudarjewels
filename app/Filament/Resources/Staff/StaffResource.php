<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Concerns\RequiresPermission;
use App\Models\User;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use UnitEnum;

class StaffResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = User::class;

    protected static ?string $permission = 'staff.manage';

    protected static ?string $viewPermission = null;

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereDoesntHave('affiliate');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->tel()->maxLength(20),
                Toggle::make('is_active')->default(true)->inline(false)->helperText('Inactive staff cannot sign in.'),
                TextInput::make('password')->password()->revealable()->rule(Password::min(12)->letters()->numbers())
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('Leave blank to keep the current password.'),
                CheckboxList::make('roles')->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', Permissions::STAFF_ROLES))
                    ->getOptionLabelFromRecordUsing(fn (Role $r) => str_replace('_', ' ', ucfirst($r->name)))->columns(2)->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('roles.name')->badge(),
            IconColumn::make('is_active')->boolean(),
            IconColumn::make('mfa')->label('2FA')->state(fn (User $u) => filled($u->app_authentication_secret))->boolean(),
            TextColumn::make('last_login_at')->since()->placeholder('Never'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
