<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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

        $this->registerGate();

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

    /**
     * Route gate checks through the resolver, granting any ability that matches
     * a permission the user holds in the scope (the first model argument). It
     * never denies — unmatched abilities fall through to the app's own gates.
     */
    private function registerGate(): void
    {
        if (! (bool) config('delegated-permissions.register_gate', true)) {
            return;
        }

        Gate::before(function (mixed $user, string $ability, array $arguments = []): ?bool {
            if (! $user instanceof Model) {
                return null;
            }

            $scope = ($arguments[0] ?? null) instanceof Model ? $arguments[0] : null;

            return $this->app->make(PermissionResolver::class)->authorizableHas($user, $ability, $scope) ? true : null;
        });
    }
}
