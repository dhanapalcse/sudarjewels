<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\ClaimController;
use App\Http\Controllers\Account\CustomerAuthController;
use App\Http\Controllers\Admin\ClaimAttachmentController as AdminClaimAttachmentController;
use App\Http\Controllers\Affiliate\AffiliateAuthController;
use App\Http\Controllers\Affiliate\DashboardController as AffiliateDashboard;
use App\Http\Controllers\Affiliate\OrderController as AffiliateOrders;
use App\Http\Controllers\Storefront\EnquiryController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SeoController;
use App\Http\Controllers\Storefront\ShopController;
use App\Http\Controllers\Warranty\WarrantyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefront (server-rendered, referral capture on every page)
|--------------------------------------------------------------------------
*/
Route::middleware('referral')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/shop', [ShopController::class, 'index'])->name('shop');
    Route::get('/category/{category:slug}', [ShopController::class, 'category'])->name('shop.category');
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

    Route::get('/about', [PageController::class, 'show'])->defaults('slug', 'about')->name('pages.about');
    Route::get('/contact', [PageController::class, 'contact'])->name('pages.contact');
    Route::get('/delivery', [PageController::class, 'show'])->defaults('slug', 'delivery')->name('pages.delivery');
    Route::get('/returns', [PageController::class, 'show'])->defaults('slug', 'returns')->name('pages.returns');
    Route::get('/privacy', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('pages.privacy');
    Route::get('/terms', [PageController::class, 'show'])->defaults('slug', 'terms')->name('pages.terms');
    Route::get('/affiliate-terms', [PageController::class, 'show'])->defaults('slug', 'affiliate-terms')->name('pages.affiliate-terms');
    Route::get('/warranty/coverage', [PageController::class, 'show'])->defaults('slug', 'warranty-coverage')->name('pages.warranty-coverage');
});

Route::post('/enquiry/whatsapp', [EnquiryController::class, 'store'])->middleware('throttle:30,1')->name('enquiry.whatsapp');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Warranty registration (generic packaging QR → /warranty)
|--------------------------------------------------------------------------
*/
Route::prefix('warranty')->name('warranty.')->middleware('noindex')->group(function () {
    Route::get('/', [WarrantyController::class, 'start'])->name('start');
    Route::post('/lookup', [WarrantyController::class, 'lookup'])->middleware('throttle:warranty-lookup')->name('lookup');
    Route::get('/verify', [WarrantyController::class, 'verifyForm'])->name('verify');
    Route::post('/verify', [WarrantyController::class, 'verify'])->middleware('throttle:otp')->name('verify.submit');
    Route::post('/resend', [WarrantyController::class, 'resend'])->middleware('throttle:otp')->name('resend');
    Route::get('/confirm', [WarrantyController::class, 'confirmForm'])->name('confirm');
    Route::post('/register', [WarrantyController::class, 'register'])->name('register');
    Route::get('/card/{registration:uuid}', [WarrantyController::class, 'card'])->name('card');
});

/*
|--------------------------------------------------------------------------
| Customer portal (phone OTP)
|--------------------------------------------------------------------------
*/
Route::prefix('account')->middleware('noindex')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
        Route::post('/login', [CustomerAuthController::class, 'sendCode'])->middleware('throttle:otp')->name('customer.login.send');
        Route::get('/login/verify', [CustomerAuthController::class, 'showVerify'])->name('customer.login.verify');
        Route::post('/login/verify', [CustomerAuthController::class, 'verify'])->middleware('throttle:otp')->name('customer.login.verify.submit');
    });
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

    Route::middleware('auth:customer')->name('account.')->group(function () {
        Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [AccountController::class, 'order'])->name('orders.show');
        Route::get('/warranties', [AccountController::class, 'warranties'])->name('warranties');
        Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
        Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');

        Route::get('/claims', [ClaimController::class, 'index'])->name('claims');
        Route::get('/claims/new', [ClaimController::class, 'create'])->name('claims.create');
        Route::post('/claims', [ClaimController::class, 'store'])->middleware('throttle:10,1')->name('claims.store');
        Route::get('/claims/{claim}', [ClaimController::class, 'show'])->name('claims.show');
        Route::post('/claims/{claim}/reply', [ClaimController::class, 'reply'])->middleware('throttle:10,1')->name('claims.reply');
        Route::get('/claims/{claim}/attachments/{attachment}', [ClaimController::class, 'attachment'])->name('claims.attachment');
    });
});

/*
|--------------------------------------------------------------------------
| Affiliate dashboard (staff use Filament at /admin)
|--------------------------------------------------------------------------
*/
Route::prefix('affiliate')->name('affiliate.')->middleware('noindex')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AffiliateAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AffiliateAuthController::class, 'login'])->middleware('throttle:affiliate-login')->name('login.submit');
        Route::get('/apply', [AffiliateAuthController::class, 'showApply'])->name('apply');
        Route::post('/apply', [AffiliateAuthController::class, 'apply'])->middleware('throttle:5,10')->name('apply.submit');
    });
    Route::post('/logout', [AffiliateAuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'affiliate'])->group(function () {
        Route::get('/', [AffiliateDashboard::class, 'overview'])->name('dashboard');
        Route::get('/catalogue', [AffiliateDashboard::class, 'catalogue'])->name('catalogue');
        Route::get('/share', [AffiliateDashboard::class, 'share'])->name('share');
        Route::get('/customers', [AffiliateDashboard::class, 'customers'])->name('customers');
        Route::get('/earnings', [AffiliateDashboard::class, 'earnings'])->name('earnings');
        Route::get('/payouts', [AffiliateDashboard::class, 'payouts'])->name('payouts');
        Route::get('/warranty', [AffiliateDashboard::class, 'warranty'])->name('warranty');
        Route::get('/profile', [AffiliateDashboard::class, 'profile'])->name('profile');
        Route::put('/profile', [AffiliateDashboard::class, 'updateProfile'])->name('profile.update');
        Route::get('/help', [AffiliateDashboard::class, 'help'])->name('help');

        Route::get('/orders', [AffiliateOrders::class, 'index'])->name('orders.index');
        Route::get('/orders/new', [AffiliateOrders::class, 'create'])->middleware('affiliate.active')->name('orders.create');
        Route::post('/orders/quote', [AffiliateOrders::class, 'quote'])->middleware(['affiliate.active', 'throttle:60,1'])->name('orders.quote');
        Route::post('/orders', [AffiliateOrders::class, 'store'])->middleware(['affiliate.active', 'throttle:20,1'])->name('orders.store');
        Route::get('/orders/{order}', [AffiliateOrders::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/edit', [AffiliateOrders::class, 'edit'])->middleware('affiliate.active')->name('orders.edit');
        Route::put('/orders/{order}', [AffiliateOrders::class, 'update'])->middleware('affiliate.active')->name('orders.update');
        Route::post('/orders/{order}/change-request', [AffiliateOrders::class, 'changeRequest'])->middleware('throttle:10,1')->name('orders.change-request');
        Route::post('/orders/{order}/acknowledge', [AffiliateOrders::class, 'acknowledgeRevision'])->name('orders.acknowledge');
    });
});

/*
|--------------------------------------------------------------------------
| Staff-only private files (Filament panel links here)
|--------------------------------------------------------------------------
*/
Route::get('/admin-files/claim-attachments/{attachment}', AdminClaimAttachmentController::class)
    ->middleware(['auth', 'noindex'])->name('admin.claim-attachment');
