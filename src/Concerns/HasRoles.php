<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Concerns;

use Fanmade\DelegatedPermissions\DelegatedPermissions;
use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * Gives a model delegated roles and permission checks. Apply it to the model
 * that holds roles (typically the User).
 *
 * @phpstan-require-extends Model
 */
trait HasRoles
{
    /**
     * The roles assigned to this model, across every scope.
     *
     * @return MorphToMany<Role, $this>
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'authorizable', DelegatedPermissions::table('role_assignments'))
            ->withTimestamps();
    }

    /**
     * Assign a role (within the role's own scope).
     */
    public function assignRole(Role $role): static
    {
        $this->resolver()->assign($this, $role);
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Remove a role assignment.
     */
    public function removeRole(Role $role): static
    {
        $this->resolver()->unassign($this, $role);
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Whether this model holds the permission within the given scope
     * (null = the global scope).
     */
    public function hasPermission(string $permission, ?Model $scope = null): bool
    {
        return $this->resolver()->authorizableHas($this, $permission, $scope);
    }

    /**
     * Every permission this model effectively holds within the given scope.
     *
     * @return Collection<int, string>
     */
    public function permissionsIn(?Model $scope = null): Collection
    {
        return $this->resolver()->permissionsForAuthorizable($this, $scope);
    }

    /**
     * Whether this model holds a role of the given name within the scope.
     */
    public function hasRole(string $name, ?Model $scope = null): bool
    {
        return $this->rolesIn($scope)->contains(static fn (Role $role): bool => $role->name === $name);
    }

    /**
     * The roles this model holds within the given scope.
     *
     * @return Collection<int, Role>
     */
    public function rolesIn(?Model $scope = null): Collection
    {
        return $this->roles
            ->filter(static function (Role $role) use ($scope): bool {
                if ($scope === null) {
                    return $role->scope_type === null && $role->scope_id === null;
                }

                return $role->scope_type === $scope->getMorphClass()
                    && (string) $role->scope_id === (string) $scope->getKey();
            })
            ->values();
    }

    /**
     * The roles this model may see and manage within a scope: the roles it holds
     * and all of their descendants, never an ancestor and never a system role.
     *
     * @return EloquentCollection<int, Role>
     */
    public function visibleRoles(?Model $scope = null): EloquentCollection
    {
        return $this->resolver()->visibleRoles($this, $scope);
    }

    protected function resolver(): PermissionResolver
    {
        return app(PermissionResolver::class);
    }
}
