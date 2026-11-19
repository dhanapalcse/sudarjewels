<?php

namespace App\Filament\Pages;

use App\Domain\Catalogue\Images\ImageProcessor;
use App\Filament\Support\MoneyInput;
use App\Support\Audit;
use App\Support\CatalogueCache;
use App\Support\Settings as SettingsStore;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use UnitEnum;

/**
 * Business settings stored in the database. Credentials and API secrets are NOT editable
 * here — they live in the server environment (.env).
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Business settings';

    protected static ?string $title = 'Business settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    private const KEYS = [
        'business.name', 'business.tagline', 'business.phone', 'business.whatsapp_number', 'business.email', 'business.address', 'business.hours', 'business.gstin',
        'social.instagram', 'social.facebook', 'social.youtube', 'social.pinterest',
        'commission.default_rate_bp', 'commission.return_window_days', 'referral.window_days',
        'warranty.months', 'warranty.replacement_policy', 'warranty.default_terms',
        'shipping.flat_fee_paise', 'shipping.free_above_paise', 'shipping.delivery_summary', 'returns.summary',
        'tax.prices_include_tax', 'tax.gst_rate_bp', 'inventory.low_stock_threshold',
        'home.hero_heading', 'home.hero_subheading', 'home.warranty_heading', 'home.warranty_text',
        'analytics.ga4_measurement_id',
    ];

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u && $u->isStaff() && $u->can('settings.manage');
    }

    public function mount(): void
    {
        $all = app(SettingsStore::class)->all();
        $state = [];
        foreach (self::KEYS as $key) {
            data_set($state, $key, $all[$key] ?? null);
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->tabs([
                Tab::make('Business')->columns(2)->schema([
                    TextInput::make('business.name')->label('Business name')->required(),
                    TextInput::make('business.tagline')->label('Tagline'),
                    TextInput::make('business.whatsapp_number')->label('WhatsApp number')->tel()->helperText('Used for product enquiries. Required before launch.'),
                    TextInput::make('business.phone')->label('Phone (display)'),
                    TextInput::make('business.email')->email(),
                    TextInput::make('business.gstin')->label('GSTIN'),
                    Textarea::make('business.address')->label('Address')->rows(3),
                    TextInput::make('business.hours')->label('Opening hours'),
                    TextInput::make('social.instagram')->url()->label('Instagram URL'),
                    TextInput::make('social.facebook')->url()->label('Facebook URL'),
                    TextInput::make('social.youtube')->url()->label('YouTube URL'),
                    TextInput::make('social.pinterest')->url()->label('Pinterest URL'),
                ]),
                Tab::make('Commission & referrals')->columns(2)->schema([
                    MoneyInput::percent('commission.default_rate_bp', 'Global default commission')->required(),
                    TextInput::make('commission.return_window_days')->label('Return window (days)')->integer()->minValue(0)->maxValue(90)
                        ->helperText('Must be set explicitly. Commission only becomes payable this many days after delivery (plus full payment). Keep it consistent with the Returns policy.'),
                    TextInput::make('referral.window_days')->label('Referral window (days)')->integer()->minValue(1)->maxValue(180)->required(),
                ]),
                Tab::make('Warranty')->columns(2)->schema([
                    TextInput::make('warranty.months')->label('Default coverage (calendar months)')->integer()->minValue(1)->maxValue(60)->required(),
                    Select::make('warranty.replacement_policy')->label('Replacement coverage')->required()->options([
                        'remaining' => 'Replacement keeps the original remaining coverage',
                        'fresh' => 'Replacement starts a new full coverage period',
                    ]),
                    Textarea::make('warranty.default_terms')->label('Default coverage terms (saved with each sale)')->rows(4)->columnSpanFull(),
                ]),
                Tab::make('Shipping & tax')->columns(2)->schema([
                    MoneyInput::make('shipping.flat_fee_paise', 'Flat shipping fee'),
                    MoneyInput::make('shipping.free_above_paise', 'Free shipping above', nullable: true),
                    Textarea::make('shipping.delivery_summary')->label('Delivery summary (product page)')->rows(2),
                    Textarea::make('returns.summary')->label('Returns summary (product page)')->rows(2),
                    Toggle::make('tax.prices_include_tax')->label('Prices include GST'),
                    MoneyInput::percent('tax.gst_rate_bp', 'GST rate')->helperText('0 = no tax breakdown. Commission is calculated excluding tax.'),
                    TextInput::make('inventory.low_stock_threshold')->label('Low-stock threshold')->integer()->minValue(0),
                ]),
                Tab::make('Homepage')->columns(2)->schema([
                    TextInput::make('home.hero_heading')->label('Hero headline'),
                    TextInput::make('home.hero_subheading')->label('Hero text'),
                    FileUpload::make('hero_upload')->label('Replace hero image')->image()->disk('local')->directory('image-staging')->visibility('private')
                        ->maxSize(15360)->helperText('Landscape, at least 1600 px wide. Optimised copies are generated on save.')->columnSpanFull(),
                    TextInput::make('home.warranty_heading')->label('Warranty highlight heading'),
                    TextInput::make('home.warranty_text')->label('Warranty highlight text'),
                ]),
                Tab::make('Analytics')->schema([
                    TextInput::make('analytics.ga4_measurement_id')->label('GA4 measurement ID')->placeholder('G-XXXXXXXXXX')->regex('/^G-[A-Z0-9]+$/')
                        ->helperText('Tracks page, product, category and WhatsApp enquiry events without personal data. The Measurement Protocol secret stays in .env (GA4_API_SECRET).'),
                ]),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save settings')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $store = app(SettingsStore::class);
        $before = $store->all();
        $values = [];
        foreach (self::KEYS as $key) {
            $value = data_get($state, $key);
            if (in_array($key, ['commission.return_window_days', 'referral.window_days', 'warranty.months', 'inventory.low_stock_threshold'], true)) {
                $value = $value === null || $value === '' ? null : (int) $value;
            }
            $values[$key] = $value;
        }
        if ($upload = data_get($state, 'hero_upload')) {
            $values['home.hero_image'] = $this->processHero(is_array($upload) ? reset($upload) : $upload);
        }
        $store->set($values);

        $changed = collect($values)->filter(fn ($v, $k) => ($before[$k] ?? null) != $v)->map(fn ($v, $k) => ['from' => $before[$k] ?? null, 'to' => $v])->except(['home.hero_image'])->all();
        Audit::log('settings.updated', null, $changed);
        CatalogueCache::flush();
        Notification::make()->title('Settings saved')->success()->send();
    }

    private function processHero(string $path): array
    {
        $binary = Storage::disk('local')->get($path);
        app(ImageProcessor::class)->validate($binary);
        $manager = new ImageManager(new Driver);
        $disk = Storage::disk(config('sudar.images.disk'));
        $version = Str::lower(Str::random(8));
        $webp = [];
        $img = $manager->read($binary);
        foreach ([640, 1024, 1600] as $w) {
            $p = "hero/hero-{$version}-{$w}.webp";
            $disk->put($p, (string) $manager->read($binary)->scaleDown(width: $w)->toWebp(quality: 80), ['visibility' => 'public']);
            $webp[(string) $w] = $p;
        }
        Storage::disk('local')->delete($path);

        return ['webp' => $webp, 'width' => $img->width(), 'height' => $img->height(), 'alt' => app(SettingsStore::class)->get('business.name').' jewellery', 'is_sample' => false];
    }
}
