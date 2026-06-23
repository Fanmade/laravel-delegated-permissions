<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

use Illuminate\Support\ServiceProvider;

final class DelegatedPermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/delegated-permissions.php', 'delegated-permissions');

        $this->app->singleton(PermissionResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/delegated-permissions.php' => $this->app->configPath('delegated-permissions.php'),
        ], 'delegated-permissions-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'delegated-permissions-migrations');
    }
}
