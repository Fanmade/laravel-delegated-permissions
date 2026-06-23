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
    $resolver = app(PermissionResolver::class);

    foreach (['manage-tags', 'create-tasks'] as $name) {
        Permission::create(['name' => $name]);
    }

    $this->system = Role::create(['name' => 'system', 'is_system' => true]);
    $this->project = Project::create(['name' => 'Apollo']);

    $this->owner = Role::create([
        'name' => 'owner',
        'parent_id' => $this->system->id,
        'scope_type' => $this->project->getMorphClass(),
        'scope_id' => $this->project->id,
    ]);
    $this->member = Role::create([
        'name' => 'member',
        'parent_id' => $this->owner->id,
        'scope_type' => $this->project->getMorphClass(),
        'scope_id' => $this->project->id,
    ]);

    foreach (['manage-tags', 'create-tasks'] as $permission) {
        $resolver->grant($this->owner, $permission);
    }
    $resolver->grant($this->member, 'create-tasks');

    $this->user = User::create(['name' => 'Casey']);
});

it('authorizes an ability through the gate when the user holds the permission', function () {
    $this->user->assignRole($this->member);

    expect($this->user->can('create-tasks', $this->project))->toBeTrue()
        ->and($this->user->can('manage-tags', $this->project))->toBeFalse();
});

it('is scope-aware in gate checks', function () {
    $other = Project::create(['name' => 'Boreas']);
    $this->user->assignRole($this->owner);

    expect($this->user->can('manage-tags', $this->project))->toBeTrue()
        ->and($this->user->can('manage-tags', $other))->toBeFalse();
});

it('falls through (denies) for abilities the package does not know', function () {
    expect($this->user->can('some-unrelated-ability', $this->project))->toBeFalse();
});

it('lets the system role authorize any scope through the gate', function () {
    $this->user->assignRole($this->system);

    expect($this->user->can('manage-tags', $this->project))->toBeTrue();
});

it('memoises a resolution within the request', function () {
    $this->user->assignRole($this->member);
    $resolver = app(PermissionResolver::class);

    $resolver->permissionsForAuthorizable($this->user, $this->project); // populate the cache

    DB::enableQueryLog();
    $resolver->permissionsForAuthorizable($this->user, $this->project); // served from cache
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

it('flushes the cache when an assignment changes', function () {
    $resolver = app(PermissionResolver::class);

    expect($resolver->permissionsForAuthorizable($this->user, $this->project))->toBeEmpty();

    $this->user->assignRole($this->member); // must invalidate the cached empty set

    expect($resolver->permissionsForAuthorizable($this->user, $this->project)->all())->toBe(['create-tasks']);
});

it('flushes the cache when a grant changes', function () {
    $this->user->assignRole($this->member);
    $resolver = app(PermissionResolver::class);

    expect($resolver->permissionsForAuthorizable($this->user, $this->project)->all())->toBe(['create-tasks']);

    $resolver->grant($this->member, 'manage-tags'); // parent (owner) holds it

    expect($resolver->permissionsForAuthorizable($this->user, $this->project)->sort()->values()->all())
        ->toBe(['create-tasks', 'manage-tags']);
});
