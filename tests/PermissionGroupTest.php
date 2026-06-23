<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Exceptions\OutOfBoundsGrant;
use Fanmade\DelegatedPermissions\Exceptions\SystemRoleException;
use Fanmade\DelegatedPermissions\Exceptions\UnknownPermissionGroup;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\PermissionGroup;
use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = app(PermissionResolver::class);

    $names = ['manage-tags', 'delete-tags', 'create-tags', 'other'];
    $this->permissions = collect($names)->mapWithKeys(static fn (string $n): array => [$n => Permission::create(['name' => $n])]);

    $this->tags = PermissionGroup::create(['name' => 'tags']);
    $this->tags->permissions()->attach([
        $this->permissions['manage-tags']->id,
        $this->permissions['delete-tags']->id,
        $this->permissions['create-tags']->id,
    ]);

    $this->system = Role::create(['name' => 'system', 'is_system' => true]);
    $this->admin = Role::create(['name' => 'admin', 'parent_id' => $this->system->id]);
    $this->member = Role::create(['name' => 'member', 'parent_id' => $this->admin->id]);
});

it('grants every permission in a group', function () {
    $this->resolver->grantGroup($this->admin, 'tags'); // parent is system → holds all

    expect($this->resolver->permissionsFor($this->admin)->sort()->values()->all())
        ->toBe(['create-tags', 'delete-tags', 'manage-tags']);
});

it('is all-or-nothing — rejects a group the parent does not fully hold', function () {
    $this->resolver->grant($this->admin, 'manage-tags'); // admin holds only one of the three

    expect(fn () => $this->resolver->grantGroup($this->member, 'tags'))->toThrow(OutOfBoundsGrant::class);

    // Nothing was granted (atomic).
    expect($this->resolver->permissionsFor($this->member))->toBeEmpty();
});

it('revokes every permission in a group, cascading to descendants', function () {
    $this->resolver->grantGroup($this->admin, 'tags');
    $this->resolver->grantGroup($this->member, 'tags');

    $this->resolver->revokeGroup($this->admin, 'tags');

    expect($this->resolver->permissionsFor($this->admin))->toBeEmpty()
        ->and($this->resolver->permissionsFor($this->member))->toBeEmpty();
});

it('still prunes a single permission that was granted via a group', function () {
    $this->resolver->grantGroup($this->admin, 'tags');

    $this->resolver->revoke($this->admin, 'delete-tags');

    expect($this->resolver->permissionsFor($this->admin)->sort()->values()->all())
        ->toBe(['create-tags', 'manage-tags']);
});

it('refuses group grant and revoke on the system role', function () {
    expect(fn () => $this->resolver->grantGroup($this->system, 'tags'))->toThrow(SystemRoleException::class)
        ->and(fn () => $this->resolver->revokeGroup($this->system, 'tags'))->toThrow(SystemRoleException::class);
});

it('throws on an unknown group', function () {
    expect(fn () => $this->resolver->grantGroup($this->admin, 'nope'))->toThrow(UnknownPermissionGroup::class);
});
