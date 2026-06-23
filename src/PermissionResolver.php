<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

use Fanmade\DelegatedPermissions\Exceptions\OrphanRole;
use Fanmade\DelegatedPermissions\Exceptions\OutOfBoundsGrant;
use Fanmade\DelegatedPermissions\Exceptions\SystemRoleException;
use Fanmade\DelegatedPermissions\Exceptions\UnknownPermission;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The constrained-delegation engine. Resolves the permissions a role
 * effectively holds and enforces the tree's invariants when granting or
 * revoking:
 *
 *  - the system role implicitly holds every permission (when enabled);
 *  - a role may only hold permissions its parent holds;
 *  - revoking a permission cascades down to every descendant;
 *  - granting a permission never cascades to descendants.
 */
final class PermissionResolver
{
    /**
     * The names of every permission the role effectively holds.
     *
     * @return Collection<int, string>
     */
    public function permissionsFor(Role $role): Collection
    {
        if ($role->is_system) {
            return DelegatedPermissions::systemEnabled()
                ? Permission::query()->orderBy('name')->pluck('name')
                : new Collection;
        }

        return $role->permissions()->orderBy('name')->pluck('name');
    }

    /**
     * Whether the role effectively holds the named permission.
     */
    public function roleHas(Role $role, string $permission): bool
    {
        return $this->permissionsFor($role)->contains($permission);
    }

    /**
     * Grant a permission to a role, enforcing that its parent holds it. The
     * grant does not cascade to the role's descendants.
     */
    public function grant(Role $role, Permission|string $permission): void
    {
        if ($role->is_system) {
            throw SystemRoleException::implicitlyHoldsEverything();
        }

        $permission = $this->resolve($permission);
        $parent = $role->parent;

        if ($parent === null) {
            throw OrphanRole::cannotDelegate($role);
        }

        if (! $this->permissionsFor($parent)->contains($permission->name)) {
            throw OutOfBoundsGrant::parentLacks($role, $permission);
        }

        $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }

    /**
     * Revoke a permission from a role and, with it, from every descendant that
     * still held it.
     */
    public function revoke(Role $role, Permission|string $permission): void
    {
        if ($role->is_system) {
            throw SystemRoleException::implicitlyHoldsEverything();
        }

        $permission = $this->resolve($permission);

        $roleIds = array_merge([(int) $role->getKey()], $this->descendantIds($role));

        DB::table(DelegatedPermissions::table('permission_role'))
            ->whereIn('role_id', $roleIds)
            ->where('permission_id', $permission->getKey())
            ->delete();
    }

    /**
     * The ids of every role beneath the given one, at any depth.
     *
     * @return array<int, int>
     */
    private function descendantIds(Role $role): array
    {
        $ids = [];

        foreach ($role->children()->get() as $child) {
            $ids[] = (int) $child->getKey();
            $ids = array_merge($ids, $this->descendantIds($child));
        }

        return $ids;
    }

    private function resolve(Permission|string $permission): Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        $model = Permission::query()->where('name', $permission)->first();

        if ($model === null) {
            throw UnknownPermission::named($permission);
        }

        return $model;
    }
}
