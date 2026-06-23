<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Exceptions;

use Fanmade\DelegatedPermissions\Models\Role;

final class OrphanRole extends DelegatedPermissionsException
{
    public static function cannotDelegate(Role $role): self
    {
        return new self(sprintf('Role "%s" is not the system role yet has no parent to delegate permissions from.', $role->name));
    }
}
