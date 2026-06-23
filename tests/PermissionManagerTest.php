<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Exceptions\UnknownPermission;
use Fanmade\DelegatedPermissions\ManagementPermission;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\PermissionGroup;
use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionManager;
use Fanmade\DelegatedPermissions\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = app(PermissionManager::class);
});

it('creates a permission', function () {
    $permission = $this->manager->createPermission('manage-tags', 'Manage tags');

    expect($permission->name)->toBe('manage-tags')
        ->and(Permission::where('name', 'manage-tags')->exists())->toBeTrue();
});

it('deletes a permission and its grants', function () {
    $resolver = app(PermissionResolver::class);
    $permission = $this->manager->createPermission('manage-tags');
    $system = Role::create(['name' => 'system', 'is_system' => true]);
    $admin = Role::create(['name' => 'admin', 'parent_id' => $system->id]);
    $resolver->grant($admin, 'manage-tags');

    $this->manager->deletePermission('manage-tags');

    expect(Permission::where('name', 'manage-tags')->exists())->toBeFalse()
        ->and(DB::table('permission_role')->count())->toBe(0);
});

it('throws when deleting an unknown permission', function () {
    expect(fn () => $this->manager->deletePermission('nope'))->toThrow(UnknownPermission::class);
});

it('installs the management permissions', function () {
    $installed = $this->manager->installManagementPermissions();

    expect($installed)->toHaveCount(count(ManagementPermission::names()))
        ->and(Permission::where('name', 'create-roles')->exists())->toBeTrue()
        ->and(Permission::where('name', 'delete-groups')->exists())->toBeTrue();

    // Idempotent.
    $this->manager->installManagementPermissions();
    expect(Permission::where('name', 'create-roles')->count())->toBe(1);
});

it('creates a group with permissions and replaces its set', function () {
    $this->manager->createPermission('manage-tags');
    $this->manager->createPermission('delete-tags');
    $this->manager->createPermission('create-tags');

    $group = $this->manager->createGroup('tags', ['manage-tags', 'delete-tags']);

    expect($group->permissions()->pluck('name')->sort()->values()->all())->toBe(['delete-tags', 'manage-tags']);

    $this->manager->setGroupPermissions($group, ['create-tags']);

    expect($group->fresh()->permissions()->pluck('name')->all())->toBe(['create-tags']);
});

it('deletes a group', function () {
    $group = $this->manager->createGroup('tags');

    $this->manager->deleteGroup('tags');

    expect(PermissionGroup::where('name', 'tags')->exists())->toBeFalse();
});
