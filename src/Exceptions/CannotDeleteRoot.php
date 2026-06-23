<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Exceptions;

use Fanmade\DelegatedPermissions\Models\Role;

final class CannotDeleteRoot extends DelegatedPermissionsException
{
    public static function role(Role $role): self
    {
        return new self(sprintf(
            'Cannot delete root role "%s" by re-parenting; delete its whole subtree instead.',
            $role->name,
        ));
    }
}
