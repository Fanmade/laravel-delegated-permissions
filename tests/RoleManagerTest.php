<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Exceptions\CannotDeleteRoot;
use Fanmade\DelegatedPermissions\Exceptions\OutOfBoundsGrant;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionResolver;
use Fanmade\DelegatedPermissions\RoleManager;
use Fanmade\DelegatedPermissions\Tests\Fixtures\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(RoleManager::class);
    $this->resolver = app(PermissionResolver::class);

    foreach (['manage-tags', 'create-tasks'] as $name) {
        Permission::create(['name' => $name]);
    }

    $this->system = Role::create(['name' => 'system', 'is_system' => true]);
    $this->project = Project::create(['name' => 'Apollo']);
});

it('creates a scoped system root', function () {
    $root = $this->manager->createSystemRole($this->project);

    expect($root->is_system)->toBeTrue()
        ->and($root->scope_type)->toBe($this->project->getMorphClass())
        ->and((int) $root->scope_id)->toBe($this->project->id);
});

it('creates a child role with validated permissions, inheriting the parent scope', function () {
    $owner = $this->manager->createRole('owner', $this->system, ['manage-tags', 'create-tasks'], $this->project);
    $member = $this->manager->createRole('member', $owner, ['create-tasks']);

    expect($member->parent_id)->toBe($owner->id)
        ->and($member->scope_type)->toBe($this->project->getMorphClass())
        ->and($this->resolver->permissionsFor($member)->all())->toBe(['create-tasks']);
});

it('rolls the whole creation back when an initial permission is out of bounds', function () {
    $owner = $this->manager->createRole('owner', $this->system, ['create-tasks'], $this->project);

    expect(fn () => $this->manager->createRole('member', $owner, ['manage-tags']))->toThrow(OutOfBoundsGrant::class);

    // The member role was not left behind.
    expect(Role::where('name', 'member')->exists())->toBeFalse();
});

it('renames a role', function () {
    $owner = $this->manager->createRole('owner', $this->system, [], $this->project);

    $this->manager->updateRole($owner, ['name' => 'lead', 'description' => 'Project lead']);

    expect($owner->fresh()->name)->toBe('lead');
});

it('ignores attributes outside the editable set', function () {
    $owner = $this->manager->createRole('owner', $this->system, [], $this->project);
    $member = $this->manager->createRole('member', $owner, [], $this->project);

    // Only name/description are editable — structural attributes are dropped, so
    // updateRole can never re-parent, re-scope or promote a role to system.
    $this->manager->updateRole($member, [
        'name' => 'lead',
        'parent_id' => $this->system->id,
        'is_system' => true,
        'scope_id' => 999,
    ]);

    $fresh = $member->fresh();

    expect($fresh->name)->toBe('lead')
        ->and($fresh->parent_id)->toBe($owner->id)
        ->and($fresh->is_system)->toBeFalse()
        ->and((int) $fresh->scope_id)->toBe($this->project->id);
});

it('re-parents children onto the grandparent when a role is deleted', function () {
    $owner = $this->manager->createRole('owner', $this->system, ['manage-tags'], $this->project);
    $member = $this->manager->createRole('member', $owner, ['manage-tags']);

    $this->manager->deleteRole($owner);

    expect(Role::whereKey($owner->id)->exists())->toBeFalse()
        ->and($member->fresh()->parent_id)->toBe($this->system->id);
});

it('refuses to delete a root by re-parenting', function () {
    expect(fn () => $this->manager->deleteRole($this->system))->toThrow(CannotDeleteRoot::class);
});

it('deletes a whole subtree', function () {
    $owner = $this->manager->createRole('owner', $this->system, ['manage-tags'], $this->project);
    $member = $this->manager->createRole('member', $owner, ['manage-tags']);

    $this->manager->deleteSubtree($owner);

    expect(Role::whereKey($owner->id)->exists())->toBeFalse()
        ->and(Role::whereKey($member->id)->exists())->toBeFalse();
});
