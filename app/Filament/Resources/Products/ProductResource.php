<?php

namespace App\Filament\Resources\Products;

use App\Enums\ProductStatus;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Support\MoneyInput;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class ProductResource extends Resource
{
    use RequiresPermission;

    protected static ?string $model = Product::class;

    protected static ?string $permission = 'catalogue.manage';

    protected static ?string $viewPermission = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $global = Money::formatRate((int) app(Settings::class)->get('commission.default_rate_bp'));

        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Details')->columns(2)->schema([
                    TextInput::make('name')->required()->maxLength(190)->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Set $set, ?Product $record) => $record ? null : $set('slug', Str::slug($state))),
                    TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true)->maxLength(190),
                    TextInput::make('sku')->label('Product SKU')->required()->unique(ignoreRecord: true)->maxLength(64),
                    Select::make('status')->options(ProductStatus::class)->default(ProductStatus::Draft)->required(),
                    Select::make('category_id')->label('Category')->required()->live()
                        ->options(fn () => Category::whereNull('parent_id')->orderBy('sort_order')->pluck('name', 'id'))
                        ->afterStateUpdated(fn (Set $set) => $set('subcategory_id', null)),
                    Select::make('subcategory_id')->label('Subcategory (optional)')
                        ->options(fn (Get $get) => Category::where('parent_id', $get('category_id'))->pluck('name', 'id')),
                    Textarea::make('short_description')->maxLength(500)->rows(2)->columnSpanFull(),
                    MarkdownEditor::make('description')->columnSpanFull(),
                    Textarea::make('materials')->rows(3),
                    Textarea::make('care_instructions')->rows(3),
                ]),
                Tab::make('Pricing & visibility')->columns(2)->schema([
                    MoneyInput::make('price_paise', 'Selling price')->required(),
                    MoneyInput::make('regular_price_paise', 'Regular price (shown struck through)', nullable: true)
                        ->rule(fn (Get $get) => function ($attr, $value, $fail) use ($get) {
                            if ($value !== null && $value !== '' && (float) $value < (float) $get('price_paise')) {
                                $fail('Regular price must be higher than the selling price.');
                            }
                        }),
                    Toggle::make('is_featured'),
                    Toggle::make('is_new_arrival'),
                    Toggle::make('is_best_seller'),
                    Toggle::make('is_sample')->label('Sample / demo product')->helperText('Shows a “Sample” label on the storefront.'),
                ]),
                Tab::make('Commission')->columns(2)->schema([
                    Toggle::make('commission_enabled')->label('Affiliate commission enabled')->default(true)->live()
                        ->helperText('Turning this off means 0% commission, overriding the global default.'),
                    MoneyInput::percent('commission_rate_bp', 'Product commission override')
                        ->disabled(fn (Get $get) => ! $get('commission_enabled'))
                        ->helperText("Leave blank to inherit the global rate ({$global}). Enter 0 for an explicit 0%."),
                ]),
                Tab::make('Warranty')->columns(2)->schema([
                    Toggle::make('warranty_eligible')->default(true),
                    TextInput::make('warranty_months')->numeric()->minValue(1)->maxValue(60)->placeholder('Default: '.app(Settings::class)->get('warranty.months'))
                        ->helperText('Leave blank to use the business default.'),
                    Textarea::make('warranty_terms')->rows(3)->columnSpanFull()->helperText('Leave blank to use the default terms from settings. Terms are saved with each sale.'),
                ]),
                Tab::make('SEO')->schema([
                    TextInput::make('seo_title')->maxLength(255),
                    TextInput::make('seo_description')->maxLength(320),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['mainImage', 'category', 'activeVariants']))
            ->columns([
                ImageColumn::make('thumb')->label('')->state(fn (Product $r) => $r->mainImage?->url(320))->square()->imageSize(48),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Product $r) => $r->sku),
                TextColumn::make('category.name')->sortable(),
                TextColumn::make('price_paise')->label('Price')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('available')->label('Available')->state(fn (Product $r) => $r->totalAvailable())
                    ->color(fn ($state) => $state <= (int) app(Settings::class)->get('inventory.low_stock_threshold') ? 'danger' : null),
                TextColumn::make('commission')->state(fn (Product $r) => ! $r->commission_enabled ? 'Off' : ($r->commission_rate_bp === null ? 'Global' : Money::formatRate($r->commission_rate_bp))),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_sample')->label('Sample')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProductStatus::class),
                SelectFilter::make('category_id')->label('Category')->relationship('category', 'name'),
                TernaryFilter::make('is_sample')->label('Sample products'),
                TernaryFilter::make('commission_enabled')->label('Commission enabled'),
            ])
            ->recordActions([
                Action::make('preview')->icon(Heroicon::OutlinedEye)->url(fn (Product $r) => route('products.show', $r), true),
                EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\VariantsRelationManager::class, RelationManagers\ImagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
