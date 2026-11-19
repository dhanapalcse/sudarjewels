<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Concerns\RequiresPermission;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = Category::class;

    protected static ?string $permission = 'catalogue.manage';

    protected static ?string $viewPermission = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Set $set, ?Category $record) => $record ? null : $set('slug', Str::slug($state))),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash()->maxLength(120),
                Select::make('parent_id')->label('Parent (leave empty for a top-level category)')
                    ->relationship('parent', 'name', fn ($query, ?Category $record) => $query->whereNull('parent_id')->when($record, fn ($query) => $query->whereKeyNot($record->id))),
                TextInput::make('sort_order')->numeric()->default(0),
                Textarea::make('description')->columnSpanFull(),
                Toggle::make('is_active')->default(true),
            ]),
            Section::make('SEO')->collapsed()->columns(2)->schema([
                TextInput::make('seo_title')->maxLength(255),
                TextInput::make('seo_description')->maxLength(320),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Category $r) => $r->parent ? 'in '.$r->parent->name : null),
                TextColumn::make('slug'),
                TextColumn::make('products_count')->counts('products')->label('Products'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
