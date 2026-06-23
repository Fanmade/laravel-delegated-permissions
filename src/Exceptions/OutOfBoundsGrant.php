<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Exceptions;

use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\Role;

final class OutOfBoundsGrant extends DelegatedPermissionsException
{
    public static function parentLacks(Role $role, Permission $permission): self
    {
        return new self(sprintf(
            'Cannot grant "%s" to role "%s": its parent does not hold that permission.',
            $permission->name,
            $role->name,
        ));
    }
}
