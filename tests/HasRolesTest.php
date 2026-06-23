<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionResolver;
use Fanmade\DelegatedPermissions\Tests\Fixtures\Project;
use Fanmade\DelegatedPermissions\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('assigns a role and exposes it through the relation', function () {
    $this->user->assignRole($this->member);

    expect($this->user->roles)->toHaveCount(1)
        ->and($this->user->hasRole('member', $this->project))->toBeTrue()
        ->and($this->user->hasRole('owner', $this->project))->toBeFalse();
});

it('checks permissions within a scope', function () {
    $this->user->assignRole($this->member);

    expect($this->user->hasPermission('create-tasks', $this->project))->toBeTrue()
        ->and($this->user->hasPermission('manage-tags', $this->project))->toBeFalse()
        ->and($this->user->permissionsIn($this->project)->all())->toBe(['create-tasks']);
});

it('removes a role', function () {
    $this->user->assignRole($this->member);
    $this->user->removeRole($this->member);

    expect($this->user->fresh()->roles)->toHaveCount(0)
        ->and($this->user->hasPermission('create-tasks', $this->project))->toBeFalse();
});

it('lists only the roles held within a scope', function () {
    $other = Project::create(['name' => 'Boreas']);

    $this->user->assignRole($this->member);

    expect($this->user->rolesIn($this->project)->pluck('name')->all())->toBe(['member'])
        ->and($this->user->rolesIn($other))->toBeEmpty()
        ->and($this->user->rolesIn(null))->toBeEmpty();
});

it('honours the system-above-all reach through the trait', function () {
    $this->user->assignRole($this->system);

    expect($this->user->hasPermission('manage-tags', $this->project))->toBeTrue()
        ->and($this->user->hasRole('system', null))->toBeTrue();
});
