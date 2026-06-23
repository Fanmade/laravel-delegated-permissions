<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Tests;

use Fanmade\DelegatedPermissions\DelegatedPermissionsServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            DelegatedPermissionsServiceProvider::class,
        ];
    }

    /**
     * Use an in-memory SQLite database by default; switch to PostgreSQL by
     * setting DB_CONNECTION=pgsql (plus the usual DB_* vars) so the suite can run
     * against both drivers.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        if (env('DB_CONNECTION') === 'pgsql') {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', ''),
                'prefix' => '',
                'search_path' => 'public',
            ]);

            return;
        }

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Fixture tables backing the test authorizable and scope models.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
