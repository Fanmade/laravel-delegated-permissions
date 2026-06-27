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

    /**
     * The cap on how many roles an authorizable may hold within one scope, or
     * null for unlimited. Both null and a negative value mean unlimited.
     */
    public static function maxRolesPerScope(): ?int
    {
        $limit = config('delegated-permissions.max_roles_per_scope');

        if ($limit === null || (int) $limit < 0) {
            return null;
        }

        return (int) $limit;
    }
}
