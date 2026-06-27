<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Exceptions;

use Fanmade\DelegatedPermissions\Models\Role;
use Illuminate\Database\Eloquent\Model;

final class RoleLimitExceeded extends DelegatedPermissionsException
{
    public static function forScope(Model $authorizable, Role $role, int $limit): self
    {
        return new self(sprintf(
            'Cannot assign role "%s" to %s #%s: it already holds the maximum of %d role(s) in this scope.',
            $role->name,
            $authorizable->getMorphClass(),
            (string) $authorizable->getKey(),
            $limit,
        ));
    }
}
