<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

use Fanmade\DelegatedPermissions\Exceptions\UnknownPermission;
use Fanmade\DelegatedPermissions\Exceptions\UnknownPermissionGroup;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\PermissionGroup;
use Illuminate\Support\Collection;

/**
 * Creates, edits and deletes permissions and permission groups. Authorization
 * is the host app's concern — gate these via the package's Gate integration.
 */
final class PermissionManager
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    public function createPermission(string $name, ?string $description = null): Permission
    {
        return Permission::create(['name' => $name, 'description' => $description]);
    }

    /**
     * Delete a permission, removing it from every role and group (grants cascade
     * away).
     */
    public function deletePermission(Permission|string $permission): void
    {
        $this->findPermission($permission)->delete();

        $this->resolver->flush();
    }

    /**
     * Seed the package's own management permissions into the catalog.
     *
     * @return Collection<int, Permission>
     */
    public function installManagementPermissions(): Collection
    {
        return collect(ManagementPermission::names())
            ->map(static fn (string $name): Permission => Permission::firstOrCreate(['name' => $name]));
    }

    /**
     * @param  array<int, Permission|string>  $permissions
     */
    public function createGroup(string $name, array $permissions = [], ?string $description = null): PermissionGroup
    {
        $group = PermissionGroup::create(['name' => $name, 'description' => $description]);

        if ($permissions !== []) {
            $this->setGroupPermissions($group, $permissions);
        }

        return $group;
    }

    /**
     * Replace a group's permissions with the given set.
     *
     * @param  array<int, Permission|string>  $permissions
     */
    public function setGroupPermissions(PermissionGroup $group, array $permissions): void
    {
        $group->permissions()->sync($this->permissionIds($permissions));
    }

    public function deleteGroup(PermissionGroup|string $group): void
    {
        $this->findGroup($group)->delete();
    }

    /**
     * @param  array<int, Permission|string>  $permissions
     * @return array<int, int>
     */
    private function permissionIds(array $permissions): array
    {
        return collect($permissions)
            ->map(fn (Permission|string $permission): int => (int) $this->findPermission($permission)->getKey())
            ->all();
    }

    private function findPermission(Permission|string $permission): Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        return Permission::query()->where('name', $permission)->first()
            ?? throw UnknownPermission::named($permission);
    }

    private function findGroup(PermissionGroup|string $group): PermissionGroup
    {
        if ($group instanceof PermissionGroup) {
            return $group;
        }

        return PermissionGroup::query()->where('name', $group)->first()
            ?? throw UnknownPermissionGroup::named($group);
    }
}
