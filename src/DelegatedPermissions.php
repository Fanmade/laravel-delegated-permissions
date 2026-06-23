<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

use Composer\InstalledVersions;

final class DelegatedPermissions
{
    /**
     * The installed package version, read from Composer's runtime so it always
     * matches the released tag.
     */
    public static function version(): string
    {
        return InstalledVersions::getPrettyVersion('fanmade/laravel-delegated-permissions') ?? 'dev';
    }

    /**
     * Resolve a configured table name, including the optional global prefix.
     */
    public static function table(string $key): string
    {
        $prefix = (string) config('delegated-permissions.table_prefix', '');
        $name = (string) config("delegated-permissions.tables.{$key}", $key);

        return $prefix.$name;
    }

    /**
     * Whether the break-glass system role is currently enabled.
     */
    public static function systemEnabled(): bool
    {
        return (bool) config('delegated-permissions.system.enabled', true);
    }
}
