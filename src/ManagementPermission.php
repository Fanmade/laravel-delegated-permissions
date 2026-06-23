<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

/**
 * The canonical permissions that gate managing the package's own roles,
 * permissions and groups. The host app grants these (typically to admin roles)
 * and gates its management surface on them, e.g. `$user->can('create-roles')`.
 */
enum ManagementPermission: string
{
    case CreateRoles = 'create-roles';
    case UpdateRoles = 'update-roles';
    case DeleteRoles = 'delete-roles';
    case AssignRoles = 'assign-roles';
    case CreatePermissions = 'create-permissions';
    case UpdatePermissions = 'update-permissions';
    case DeletePermissions = 'delete-permissions';
    case CreateGroups = 'create-groups';
    case UpdateGroups = 'update-groups';
    case DeleteGroups = 'delete-groups';

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
