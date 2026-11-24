<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Support\StoreSettings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
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
            ->brandName(fn (): string => StoreSettings::brandName())
            ->path('admin')
            ->login()
            ->profile()
            ->multiFactorAuthentication(fn (): array => config('store.admin_mfa_enabled') ? [AppAuthentication::make()] : [])
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->renderHook('panels::topbar.end', fn () => view('filament.price-view'))
            ->renderHook('panels::topbar.end', fn () => view('filament.order-alerts'))
            ->unsavedChangesAlerts()
            ->navigationGroups(array_map(function (string $label): NavigationGroup {
                $group = NavigationGroup::make($label);

                return $group->collapsed(fn (): bool => ! $group->isActive())->collapsible();
            }, ['Analytics', 'Catalog', 'Orders & customers', 'Purchasing', 'Marketing', 'Storefront', 'Settings']))
            ->renderHook('panels::page.start', fn () => view('filament.page-help'))
            ->renderHook('panels::head.end', fn () => view('filament.admin-assets'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
