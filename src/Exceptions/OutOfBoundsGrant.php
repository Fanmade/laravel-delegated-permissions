<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Exceptions;

use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\PermissionGroup;
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

    /**
     * @param  array<int, string>  $missing
     */
    public static function groupExceedsParent(Role $role, PermissionGroup $group, array $missing): self
    {
        return new self(sprintf(
            'Cannot grant group "%s" to role "%s": its parent does not hold %s.',
            $group->name,
            $role->name,
            implode(', ', $missing),
        ));
    }
}
