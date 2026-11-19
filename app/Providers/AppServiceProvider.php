<?php

namespace App\Providers;

use App\Domain\Notifications\Sms\Msg91Gateway;
use App\Domain\Notifications\Sms\SmsGateway;
use App\Domain\Notifications\Sms\TestSmsGateway;
use App\Domain\Notifications\Sms\TwilioGateway;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\PurchasedUnit;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WarrantyClaim;
use App\Models\WarrantyRegistration;
use App\Support\CatalogueCache;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        $this->app->singleton(SmsGateway::class, function () {
            $driver = config('sudar.notifications.sms_driver');
            if ($driver === 'test' && $this->app->environment('production')) {
                throw new RuntimeException('SMS_DRIVER=test is not allowed in production. Configure msg91 or twilio.');
            }

            return match ($driver) {
                'msg91' => new Msg91Gateway(config('sudar.notifications.msg91')),
                'twilio' => new TwilioGateway(config('sudar.notifications.twilio')),
                default => new TestSmsGateway,
            };
        });
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'customer' => Customer::class,
            'order' => Order::class,
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'product_image' => ProductImage::class,
            'shipment' => Shipment::class,
            'purchased_unit' => PurchasedUnit::class,
            'affiliate' => Affiliate::class,
            'affiliate_payout' => AffiliatePayout::class,
            'commission_entry' => CommissionEntry::class,
            'warranty_registration' => WarrantyRegistration::class,
            'warranty_claim' => WarrantyClaim::class,
            'refund' => Refund::class,
            'payment' => Payment::class,
            'category' => Category::class,
            'setting' => Setting::class,
            'page' => Page::class,
        ]);

        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole(Permissions::SUPER_ADMIN) && $user->is_active ? true : null);

        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(config('sudar.otp.lookup_attempts_per_minute'))->by('otp-ip:'.$request->ip()),
            Limit::perHour(30)->by('otp-ip-hour:'.$request->ip()),
        ]);
        RateLimiter::for('warranty-lookup', fn (Request $request) => [
            Limit::perMinute(config('sudar.otp.lookup_attempts_per_minute'))->by('wl-ip:'.$request->ip()),
            Limit::perHour(30)->by('wl-ip-hour:'.$request->ip()),
            Limit::perHour(10)->by('wl-order:'.strtoupper((string) $request->input('order_number'))),
        ]);
        RateLimiter::for('affiliate-login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        foreach ([Product::class, ProductVariant::class, Category::class, ProductImage::class] as $model) {
            $model::saved(fn () => CatalogueCache::flush());
            $model::deleted(fn () => CatalogueCache::flush());
        }

        View::composer(['layouts.*', 'partials.*', 'storefront.*', 'account.*', 'affiliate.*', 'warranty.*', 'components.*'], function ($view) {
            $view->with('settings', app(Settings::class));
        });
        View::composer(['partials.header', 'partials.footer'], function ($view) {
            $view->with('navCategories', CatalogueCache::remember('nav-categories', 3600, fn () => Category::active()->topLevel()->orderBy('sort_order')->limit(8)->get()));
        });
    }
}
