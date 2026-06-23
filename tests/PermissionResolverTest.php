<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Exceptions\OrphanRole;
use Fanmade\DelegatedPermissions\Exceptions\OutOfBoundsGrant;
use Fanmade\DelegatedPermissions\Exceptions\SystemRoleException;
use Fanmade\DelegatedPermissions\Exceptions\UnknownPermission;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = app(PermissionResolver::class);

    foreach (['a', 'b', 'c', 'd'] as $name) {
        Permission::create(['name' => $name]);
    }

    // system → admin → member
    $this->system = Role::create(['name' => 'system', 'is_system' => true]);
    $this->admin = Role::create(['name' => 'admin', 'parent_id' => $this->system->id]);
    $this->member = Role::create(['name' => 'member', 'parent_id' => $this->admin->id]);
});

it('gives the system role every permission when enabled', function () {
    expect($this->resolver->permissionsFor($this->system)->all())->toBe(['a', 'b', 'c', 'd'])
        ->and($this->resolver->roleHas($this->system, 'c'))->toBeTrue();
});

it('gives the system role nothing when disabled', function () {
    config()->set('delegated-permissions.system.enabled', false);

    expect($this->resolver->permissionsFor($this->system))->toBeEmpty()
        ->and($this->resolver->roleHas($this->system, 'a'))->toBeFalse();
});

it('grants a permission the parent holds', function () {
    $this->resolver->grant($this->admin, 'a'); // parent is system → holds everything

    expect($this->resolver->roleHas($this->admin, 'a'))->toBeTrue();
});

it('rejects granting a permission the parent lacks', function () {
    $this->resolver->grant($this->admin, 'a'); // admin holds only "a"

    expect(fn () => $this->resolver->grant($this->member, 'b'))->toThrow(OutOfBoundsGrant::class);

    // ...but a permission the parent does hold is allowed.
    $this->resolver->grant($this->member, 'a');
    expect($this->resolver->roleHas($this->member, 'a'))->toBeTrue();
});

it('does not cascade a grant to descendants', function () {
    $this->resolver->grant($this->admin, 'a');
    $this->resolver->grant($this->member, 'a');

    $this->resolver->grant($this->admin, 'b'); // admin gains "b"; member must not

    expect($this->resolver->roleHas($this->admin, 'b'))->toBeTrue()
        ->and($this->resolver->roleHas($this->member, 'b'))->toBeFalse();
});

it('cascades a revoke down to descendants', function () {
    $this->resolver->grant($this->admin, 'a');
    $this->resolver->grant($this->member, 'a');

    $this->resolver->revoke($this->admin, 'a');

    expect($this->resolver->roleHas($this->admin, 'a'))->toBeFalse()
        ->and($this->resolver->roleHas($this->member, 'a'))->toBeFalse();
});

it('cascades a revoke through a deep chain', function () {
    $guest = Role::create(['name' => 'guest', 'parent_id' => $this->member->id]);

    foreach ([$this->admin, $this->member, $guest] as $role) {
        $this->resolver->grant($role, 'a');
    }

    $this->resolver->revoke($this->admin, 'a');

    foreach ([$this->admin, $this->member, $guest] as $role) {
        expect($this->resolver->roleHas($role, 'a'))->toBeFalse();
    }
});

it('only revokes from the subtree, leaving siblings untouched', function () {
    $sibling = Role::create(['name' => 'auditor', 'parent_id' => $this->system->id]);
    $this->resolver->grant($this->admin, 'a');
    $this->resolver->grant($sibling, 'a');

    $this->resolver->revoke($this->admin, 'a');

    expect($this->resolver->roleHas($this->admin, 'a'))->toBeFalse()
        ->and($this->resolver->roleHas($sibling, 'a'))->toBeTrue();
});

it('refuses to grant or revoke on the system role', function () {
    expect(fn () => $this->resolver->grant($this->system, 'a'))->toThrow(SystemRoleException::class)
        ->and(fn () => $this->resolver->revoke($this->system, 'a'))->toThrow(SystemRoleException::class);
});

it('gives a non-system role only its own grants, never its parent\'s', function () {
    $this->resolver->grant($this->admin, 'a');
    $this->resolver->grant($this->admin, 'b');

    expect($this->resolver->permissionsFor($this->member))->toBeEmpty();
});

it('throws on an unknown permission', function () {
    expect(fn () => $this->resolver->grant($this->admin, 'does-not-exist'))->toThrow(UnknownPermission::class);
});

it('throws when a non-system role has no parent to delegate from', function () {
    $orphan = Role::create(['name' => 'stray']); // no parent, not system

    expect(fn () => $this->resolver->grant($orphan, 'a'))->toThrow(OrphanRole::class);
});

it('is idempotent when granting the same permission twice', function () {
    $this->resolver->grant($this->admin, 'a');
    $this->resolver->grant($this->admin, 'a');

    expect($this->admin->permissions()->count())->toBe(1);
});
