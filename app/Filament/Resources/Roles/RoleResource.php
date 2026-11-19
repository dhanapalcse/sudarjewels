<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Concerns\RequiresPermission;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = Role::class;

    protected static ?string $permission = 'staff.manage';

    protected static ?string $viewPermission = null;

    protected static ?string $navigationLabel = 'Staff permissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotIn('name', [Permissions::SUPER_ADMIN, Permissions::AFFILIATE]);
    }

    public static function canCreate(): bool
    {
        return false; // roles map to STAFF_ROLES; permissions per role are editable
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->disabled(),
            CheckboxList::make('permissions')->relationship('permissions', 'name')
                ->getOptionLabelFromRecordUsing(fn (Model $p) => Permissions::ALL[$p->name] ?? $p->name)
                ->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state))),
            TextColumn::make('permissions_count')->counts('permissions')->label('Permissions'),
            TextColumn::make('users_count')->counts('users')->label('Staff'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
