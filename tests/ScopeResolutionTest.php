<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionResolver;
use Fanmade\DelegatedPermissions\Tests\Fixtures\Project;
use Fanmade\DelegatedPermissions\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = app(PermissionResolver::class);

    foreach (['manage-tags', 'delete-tasks', 'create-tasks'] as $name) {
        Permission::create(['name' => $name]);
    }

    // The global system role (scope = null).
    $this->system = Role::create(['name' => 'system', 'is_system' => true]);

    $this->projectA = Project::create(['name' => 'Apollo']);
    $this->projectB = Project::create(['name' => 'Boreas']);

    // Project A's tree hangs off the system role, scoped to project A.
    $this->ownerA = Role::create([
        'name' => 'owner',
        'parent_id' => $this->system->id,
        'scope_type' => $this->projectA->getMorphClass(),
        'scope_id' => $this->projectA->id,
    ]);
    $this->memberA = Role::create([
        'name' => 'member',
        'parent_id' => $this->ownerA->id,
        'scope_type' => $this->projectA->getMorphClass(),
        'scope_id' => $this->projectA->id,
    ]);

    foreach (['manage-tags', 'delete-tasks', 'create-tasks'] as $permission) {
        $this->resolver->grant($this->ownerA, $permission);
    }
    $this->resolver->grant($this->memberA, 'create-tasks');

    $this->user = User::create(['name' => 'Casey']);
});

it('resolves a user\'s permissions within a project scope', function () {
    $this->resolver->assign($this->user, $this->memberA);

    expect($this->resolver->permissionsForAuthorizable($this->user, $this->projectA)->all())->toBe(['create-tasks'])
        ->and($this->resolver->authorizableHas($this->user, 'create-tasks', $this->projectA))->toBeTrue()
        ->and($this->resolver->authorizableHas($this->user, 'delete-tasks', $this->projectA))->toBeFalse();
});

it('isolates scopes — a role in project A grants nothing in project B', function () {
    $this->resolver->assign($this->user, $this->ownerA);

    expect($this->resolver->permissionsForAuthorizable($this->user, $this->projectA)->sort()->values()->all())
        ->toBe(['create-tasks', 'delete-tasks', 'manage-tags'])
        ->and($this->resolver->permissionsForAuthorizable($this->user, $this->projectB))->toBeEmpty();
});

it('lets the system role reach every scope when above-all is enabled', function () {
    $this->resolver->assign($this->user, $this->system);

    expect($this->resolver->authorizableHas($this->user, 'manage-tags', $this->projectA))->toBeTrue()
        ->and($this->resolver->authorizableHas($this->user, 'delete-tasks', $this->projectB))->toBeTrue();
});

it('confines the system role to its own scope when above-all is disabled', function () {
    config()->set('delegated-permissions.system.scope_above_all', false);
    $this->resolver->assign($this->user, $this->system);

    expect($this->resolver->permissionsForAuthorizable($this->user, $this->projectA))->toBeEmpty()
        // ...but in the global scope it still holds everything.
        ->and($this->resolver->permissionsForAuthorizable($this->user, null)->count())->toBe(3);
});

it('grants nothing anywhere when the system role is disabled', function () {
    config()->set('delegated-permissions.system.enabled', false);
    $this->resolver->assign($this->user, $this->system);

    expect($this->resolver->permissionsForAuthorizable($this->user, $this->projectA))->toBeEmpty()
        ->and($this->resolver->permissionsForAuthorizable($this->user, null))->toBeEmpty();
});

it('loads a user\'s assigned roles once per request across multiple scopes', function () {
    $this->resolver->assign($this->user, $this->ownerA);

    DB::enableQueryLog();

    // Resolve the same user against three distinct scopes in one request.
    $this->resolver->permissionsForAuthorizable($this->user, $this->projectA);
    $this->resolver->permissionsForAuthorizable($this->user, $this->projectB);
    $this->resolver->permissionsForAuthorizable($this->user, null);

    $roleLoads = collect(DB::getQueryLog())
        ->filter(static fn (array $query): bool => str_contains($query['query'], 'role_assignments'))
        ->count();
    DB::disableQueryLog();

    // The role set does not vary by scope, so it is loaded a single time.
    expect($roleLoads)->toBe(1);
});

it('reloads assigned roles after an assignment change flushes the cache', function () {
    $this->resolver->assign($this->user, $this->memberA);
    expect($this->resolver->assignedRoles($this->user)->count())->toBe(1);

    // A further assignment must invalidate the memoised role set, not serve it stale.
    $this->resolver->assign($this->user, $this->ownerA);
    expect($this->resolver->assignedRoles($this->user)->count())->toBe(2);

    $this->resolver->unassign($this->user, $this->ownerA);
    expect($this->resolver->assignedRoles($this->user)->count())->toBe(1);
});

it('assigns and unassigns roles idempotently', function () {
    $this->resolver->assign($this->user, $this->memberA);
    $this->resolver->assign($this->user, $this->memberA);

    expect($this->resolver->assignedRoles($this->user)->count())->toBe(1);

    $this->resolver->unassign($this->user, $this->memberA);

    expect($this->resolver->assignedRoles($this->user)->count())->toBe(0);
});
