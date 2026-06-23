<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

final class DelegatedPermissions
{
    public const string VERSION = '0.0.1-dev';

    public static function version(): string
    {
        return self::VERSION;
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
