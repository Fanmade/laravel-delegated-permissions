<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\DelegatedPermissions;
use Fanmade\DelegatedPermissions\DelegatedPermissionsServiceProvider;

it('boots the service provider and merges its config', function () {
    expect(app()->getLoadedProviders())
        ->toHaveKey(DelegatedPermissionsServiceProvider::class)
        ->and(config('delegated-permissions.system_role'))->toBe('system')
        ->and(config('delegated-permissions.system_scope_above_all'))->toBeTrue();
});

it('exposes a version string', function () {
    expect(DelegatedPermissions::version())->toBeString()->not->toBeEmpty();
});
