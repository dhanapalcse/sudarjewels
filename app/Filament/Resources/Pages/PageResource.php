<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Concerns\RequiresPermission;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
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

class PageResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = Page::class;

    protected static ?string $permission = 'content.manage';

    protected static ?string $viewPermission = null;

    protected static ?string $navigationLabel = 'Pages & policies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title')->required()->maxLength(190),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true)->disabledOn('edit'),
                MarkdownEditor::make('body')->columnSpanFull()->required(),
                Toggle::make('is_published')->default(true),
                Toggle::make('needs_review')->label('Contains placeholder text')->helperText('Turn off once real, reviewed policy content is in place. Launch checks fail while any page is flagged.'),
            ]),
            Section::make('SEO')->collapsed()->columns(2)->schema([
                TextInput::make('seo_title'),
                TextInput::make('seo_description')->maxLength(320),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('slug'),
            IconColumn::make('is_published')->boolean(),
            IconColumn::make('needs_review')->label('Placeholder')->boolean()->trueColor('danger')->falseColor('success'),
            TextColumn::make('updated_at')->since(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PageResource\ListPages::route('/'),
            'create' => PageResource\CreatePage::route('/create'),
            'edit' => PageResource\EditPage::route('/{record}/edit'),
        ];
    }
}
