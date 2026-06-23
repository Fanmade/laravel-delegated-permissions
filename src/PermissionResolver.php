<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

use Fanmade\DelegatedPermissions\Exceptions\OrphanRole;
use Fanmade\DelegatedPermissions\Exceptions\OutOfBoundsGrant;
use Fanmade\DelegatedPermissions\Exceptions\SystemRoleException;
use Fanmade\DelegatedPermissions\Exceptions\UnknownPermission;
use Fanmade\DelegatedPermissions\Exceptions\UnknownPermissionGroup;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\PermissionGroup;
use Fanmade\DelegatedPermissions\Models\Role;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
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
     * Per-request memoised authorizable resolutions, keyed by authorizable+scope.
     *
     * @var array<string, Collection<int, string>>
     */
    private array $resolved = [];

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

        $this->flush();
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

        $this->flush();
    }

    /**
     * Grant every permission in a group to a role. All-or-nothing: the role's
     * parent must effectively hold every permission in the group, otherwise the
     * whole grant is rejected and nothing changes.
     */
    public function grantGroup(Role $role, PermissionGroup|string $group): void
    {
        if ($role->is_system) {
            throw SystemRoleException::implicitlyHoldsEverything();
        }

        $group = $this->resolveGroup($group);
        $parent = $role->parent;

        if ($parent === null) {
            throw OrphanRole::cannotDelegate($role);
        }

        $permissions = $group->permissions()->get();
        $parentPermissions = $this->permissionsFor($parent);

        $missing = $permissions->reject(static fn (Permission $permission): bool => $parentPermissions->contains($permission->name));

        if ($missing->isNotEmpty()) {
            throw OutOfBoundsGrant::groupExceedsParent($role, $group, $missing->pluck('name')->all());
        }

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

        $this->flush();
    }

    /**
     * Revoke every permission in a group from a role and its descendants.
     */
    public function revokeGroup(Role $role, PermissionGroup|string $group): void
    {
        if ($role->is_system) {
            throw SystemRoleException::implicitlyHoldsEverything();
        }

        $group = $this->resolveGroup($group);

        $permissionIds = $group->permissions()->pluck(DelegatedPermissions::table('permissions').'.id')->all();
        $roleIds = array_merge([(int) $role->getKey()], $this->descendantIds($role));

        DB::table(DelegatedPermissions::table('permission_role'))
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        $this->flush();
    }

    /**
     * Assign a role to an authorizable (idempotent).
     */
    public function assign(Model $authorizable, Role $role): void
    {
        DB::table(DelegatedPermissions::table('role_assignments'))->insertOrIgnore([
            'role_id' => $role->getKey(),
            'authorizable_type' => $authorizable->getMorphClass(),
            'authorizable_id' => $authorizable->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->flush();
    }

    /**
     * Remove a role assignment from an authorizable.
     */
    public function unassign(Model $authorizable, Role $role): void
    {
        DB::table(DelegatedPermissions::table('role_assignments'))
            ->where('role_id', $role->getKey())
            ->where('authorizable_type', $authorizable->getMorphClass())
            ->where('authorizable_id', $authorizable->getKey())
            ->delete();

        $this->flush();
    }

    /**
     * The roles assigned to an authorizable.
     *
     * @return EloquentCollection<int, Role>
     */
    public function assignedRoles(Model $authorizable): EloquentCollection
    {
        $roleIds = DB::table(DelegatedPermissions::table('role_assignments'))
            ->where('authorizable_type', $authorizable->getMorphClass())
            ->where('authorizable_id', $authorizable->getKey())
            ->pluck('role_id');

        return Role::query()->whereIn('id', $roleIds)->get();
    }

    /**
     * Every permission the authorizable effectively holds within the given scope
     * (null = the global scope). Memoised for the request.
     *
     * @return Collection<int, string>
     */
    public function permissionsForAuthorizable(Model $authorizable, ?Model $scope = null): Collection
    {
        return $this->resolved[$this->cacheKey($authorizable, $scope)] ??= $this->resolvePermissions($authorizable, $scope);
    }

    /**
     * Whether the authorizable effectively holds the permission within the scope.
     */
    public function authorizableHas(Model $authorizable, string $permission, ?Model $scope = null): bool
    {
        return $this->permissionsForAuthorizable($authorizable, $scope)->contains($permission);
    }

    /**
     * Drop the memoised authorizable resolutions (called after any change).
     */
    public function flush(): void
    {
        $this->resolved = [];
    }

    /**
     * Compute (uncached) the permissions an authorizable holds within a scope.
     *
     * @return Collection<int, string>
     */
    private function resolvePermissions(Model $authorizable, ?Model $scope): Collection
    {
        $roles = $this->assignedRoles($authorizable);

        $permissions = $roles
            ->filter(fn (Role $role): bool => $this->roleMatchesScope($role, $scope))
            ->flatMap(fn (Role $role): Collection => $this->permissionsFor($role));

        if ($scope !== null && $this->systemAboveAllActive()) {
            $fromSystem = $roles
                ->filter(static fn (Role $role): bool => $role->is_system)
                ->flatMap(fn (Role $role): Collection => $this->permissionsFor($role));

            $permissions = $permissions->merge($fromSystem);
        }

        return $permissions->unique()->values();
    }

    /**
     * Whether a role belongs to the given scope (null = the global scope).
     */
    private function roleMatchesScope(Role $role, ?Model $scope): bool
    {
        if ($scope === null) {
            return $role->scope_type === null && $role->scope_id === null;
        }

        return $role->scope_type === $scope->getMorphClass()
            && (int) $role->scope_id === (int) $scope->getKey();
    }

    /**
     * Whether the system role currently reaches every scope.
     */
    private function systemAboveAllActive(): bool
    {
        return DelegatedPermissions::systemEnabled()
            && (bool) config('delegated-permissions.system.scope_above_all', true);
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

    private function resolveGroup(PermissionGroup|string $group): PermissionGroup
    {
        if ($group instanceof PermissionGroup) {
            return $group;
        }

        $model = PermissionGroup::query()->where('name', $group)->first();

        if ($model === null) {
            throw UnknownPermissionGroup::named($group);
        }

        return $model;
    }

    /**
     * A stable key for memoising a resolution; includes the system flags so a
     * config change cannot return a stale set.
     */
    private function cacheKey(Model $authorizable, ?Model $scope): string
    {
        $who = $authorizable->getMorphClass().'#'.$authorizable->getKey();
        $where = $scope === null ? 'global' : $scope->getMorphClass().'#'.$scope->getKey();
        $flags = (DelegatedPermissions::systemEnabled() ? 'E' : 'e')
            .(config('delegated-permissions.system.scope_above_all') ? 'A' : 'a');

        return $who.'|'.$where.'|'.$flags;
    }
}
