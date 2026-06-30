<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions;

use Fanmade\DelegatedPermissions\Exceptions\CannotDeleteRoot;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Creates, edits and deletes roles within the delegation tree. Authorization
 * (e.g. {@see ManagementPermission::CreateRoles}) is the host app's concern —
 * gate these calls via the package's Gate integration.
 */
final class RoleManager
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Create the root (system) role for a scope (null = the global scope).
     */
    public function createSystemRole(?Model $scope = null): Role
    {
        return Role::create([
            'name' => (string) config('delegated-permissions.system.role', 'system'),
            'is_system' => true,
            'scope_type' => $scope?->getMorphClass(),
            'scope_id' => $scope?->getKey(),
        ]);
    }

    /**
     * Create a child role under the given parent, granting initial permissions
     * (each validated against the parent). The scope defaults to the parent's.
     * Atomic: an out-of-bounds permission rolls the whole creation back.
     *
     * @param  array<int, Permission|string>  $permissions
     */
    public function createRole(string $name, Role $parent, array $permissions = [], ?Model $scope = null): Role
    {
        return DB::transaction(function () use ($name, $parent, $permissions, $scope): Role {
            $role = Role::create([
                'name' => $name,
                'parent_id' => $parent->getKey(),
                'scope_type' => $scope?->getMorphClass() ?? $parent->scope_type,
                'scope_id' => $scope?->getKey() ?? $parent->scope_id,
            ]);

            foreach ($permissions as $permission) {
                $this->resolver->grant($role, $permission);
            }

            return $role;
        });
    }

    /**
     * A role's user-editable attributes. Its structural attributes — parent,
     * scope and permission set — are intentionally not editable here; those are
     * managed through {@see createRole()}/{@see deleteRole()} and the resolver's
     * grant/revoke methods, which enforce the delegation invariants.
     *
     * @var list<string>
     */
    private const array EDITABLE_ATTRIBUTES = ['name', 'description'];

    /**
     * Rename or re-describe a role. Any attribute outside
     * {@see self::EDITABLE_ATTRIBUTES} is deliberately ignored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateRole(Role $role, array $attributes): Role
    {
        $role->update(collect($attributes)->only(self::EDITABLE_ATTRIBUTES)->all());

        return $role;
    }

    /**
     * Delete a role, re-parenting its children onto the role's parent. Their
     * delegated permissions stay valid (a child's set is already a subset of the
     * grandparent's). Throws for a root — use {@see deleteSubtree()} instead.
     */
    public function deleteRole(Role $role): void
    {
        if ($role->parent_id === null) {
            throw CannotDeleteRoot::role($role);
        }

        $role->children()->update(['parent_id' => $role->parent_id]);
        $role->delete();

        $this->resolver->flush();
    }

    /**
     * Delete a role together with its whole subtree (grants and assignments
     * cascade away with it).
     */
    public function deleteSubtree(Role $role): void
    {
        $role->delete();

        $this->resolver->flush();
    }
}
