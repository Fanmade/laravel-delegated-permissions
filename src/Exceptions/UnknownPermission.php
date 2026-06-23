<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Exceptions;

final class UnknownPermission extends DelegatedPermissionsException
{
    public static function named(string $name): self
    {
        return new self(sprintf('Unknown permission "%s".', $name));
    }
}
