<?php

namespace App\Providers\Filament;

use App\Http\Middleware\NoIndex;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            // Staff must enrol an authenticator app (TOTP) before using the dashboard.
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable()->brandName('SUDAR JEWELS'),
            ], isRequired: (bool) config('sudar.admin_require_2fa', true))
            ->brandName('SUDAR JEWELS')
            ->font('DM Sans')
            ->colors([
                'primary' => Color::hex('#145A58'),
                'gray' => Color::Stone,
            ])
            ->navigationGroups([
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Catalogue'),
                NavigationGroup::make('Affiliates'),
                NavigationGroup::make('Warranty'),
                NavigationGroup::make('Website'),
                NavigationGroup::make('Administration'),
            ])
            ->databaseNotifications(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                NoIndex::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
