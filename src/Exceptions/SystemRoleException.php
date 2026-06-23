<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Exceptions;

final class SystemRoleException extends DelegatedPermissionsException
{
    public static function implicitlyHoldsEverything(): self
    {
        return new self('The system role implicitly holds every permission; it cannot be granted or revoked individually.');
    }
}
