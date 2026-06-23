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
}
