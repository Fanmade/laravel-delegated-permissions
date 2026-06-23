<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\DelegatedPermissions;
use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\PermissionGroup;
use Fanmade\DelegatedPermissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates every package table', function () {
    foreach (['permissions', 'permission_groups', 'permission_group_permission', 'roles', 'permission_role', 'role_assignments'] as $key) {
        expect(Schema::hasTable(DelegatedPermissions::table($key)))->toBeTrue();
    }
});

it('relates permissions to the groups that bundle them', function () {
    $manage = Permission::create(['name' => 'manage-tags']);
    $delete = Permission::create(['name' => 'delete-tags']);
    $group = PermissionGroup::create(['name' => 'tags']);

    $group->permissions()->attach([$manage->id, $delete->id]);

    expect($group->permissions()->pluck('name')->sort()->values()->all())->toBe(['delete-tags', 'manage-tags'])
        ->and($manage->groups()->pluck('name')->all())->toBe(['tags']);
});

it('forms a role tree with a parent, children and permissions', function () {
    $permission = Permission::create(['name' => 'manage-tags']);

    $system = Role::create(['name' => 'system', 'is_system' => true]);
    $admin = Role::create(['name' => 'admin', 'parent_id' => $system->id]);
    $admin->permissions()->attach($permission);

    expect($system->is_system)->toBeTrue()
        ->and($admin->parent->is($system))->toBeTrue()
        ->and($system->children->pluck('name')->all())->toBe(['admin'])
        ->and($admin->permissions->pluck('name')->all())->toBe(['manage-tags']);
});

it('scopes a role to a model polymorphically, or globally', function () {
    // Use a permission row as an arbitrary scope target to exercise the morph.
    $target = Permission::create(['name' => 'scope-target']);

    $scoped = Role::create([
        'name' => 'member',
        'scope_type' => $target->getMorphClass(),
        'scope_id' => $target->getKey(),
    ]);
    $global = Role::create(['name' => 'system']);

    expect($scoped->scope->is($target))->toBeTrue()
        ->and($global->scope)->toBeNull();
});

it('cascades role deletion to its grants and assignments', function () {
    $permission = Permission::create(['name' => 'manage-tags']);
    $role = Role::create(['name' => 'admin']);
    $role->permissions()->attach($permission);

    $role->delete();

    $grantRows = DB::table(DelegatedPermissions::table('permission_role'))->count();

    expect($grantRows)->toBe(0)
        // The permission itself survives the role.
        ->and(Permission::whereKey($permission->id)->exists())->toBeTrue();
});
