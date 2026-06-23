<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\DelegatedPermissions;
use Fanmade\DelegatedPermissions\DelegatedPermissionsServiceProvider;

it('boots the service provider and merges its config', function () {
    expect(app()->getLoadedProviders())->toHaveKey(DelegatedPermissionsServiceProvider::class)
        ->and(config('delegated-permissions.system.role'))->toBe('system')
        ->and(config('delegated-permissions.system.enabled'))->toBeTrue();
});

it('exposes a version string', function () {
    expect(DelegatedPermissions::version())->toBeString()->not->toBeEmpty();
});

it('composes table names with the configurable prefix', function () {
    expect(DelegatedPermissions::table('roles'))->toBe('roles');

    config()->set('delegated-permissions.table_prefix', 'dp_');

    expect(DelegatedPermissions::table('roles'))->toBe('dp_roles')
        ->and(DelegatedPermissions::table('permission_role'))->toBe('dp_permission_role');
});

it('reports whether the system role is enabled', function () {
    expect(DelegatedPermissions::systemEnabled())->toBeTrue();

    config()->set('delegated-permissions.system.enabled', false);

    expect(DelegatedPermissions::systemEnabled())->toBeFalse();
});
