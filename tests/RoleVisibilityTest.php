<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\Tests\Fixtures\Project;
use Fanmade\DelegatedPermissions\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::create(['name' => 'Apollo']);
    $this->system = Role::create(['name' => 'system', 'is_system' => true]);

    $scope = ['scope_type' => $this->project->getMorphClass(), 'scope_id' => $this->project->id];

    // system → owner → admin → member → viewer, plus a custom "reviewer" under admin.
    $this->owner = Role::create(['name' => 'owner', 'parent_id' => $this->system->id, ...$scope]);
    $this->admin = Role::create(['name' => 'admin', 'parent_id' => $this->owner->id, ...$scope]);
    $this->member = Role::create(['name' => 'member', 'parent_id' => $this->admin->id, ...$scope]);
    $this->viewer = Role::create(['name' => 'viewer', 'parent_id' => $this->member->id, ...$scope]);
    $this->reviewer = Role::create(['name' => 'reviewer', 'parent_id' => $this->admin->id, ...$scope]);
});

it('shows a holder only their role and its descendants', function () {
    $user = User::create(['name' => 'Casey']);
    $user->assignRole($this->member);

    expect($user->visibleRoles($this->project)->pluck('name')->sort()->values()->all())
        ->toBe(['member', 'viewer']);
});

it('hides ancestors and the system role', function () {
    $user = User::create(['name' => 'Dana']);
    $user->assignRole($this->admin);

    $names = $user->visibleRoles($this->project)->pluck('name')->all();

    expect($names)->toContain('admin', 'member', 'viewer', 'reviewer')
        ->and($names)->not->toContain('owner')
        ->and($names)->not->toContain('system');
});

it('shows an owner the whole project tree but never the system root', function () {
    $user = User::create(['name' => 'Erin']);
    $user->assignRole($this->owner);

    expect($user->visibleRoles($this->project)->pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'member', 'owner', 'reviewer', 'viewer']);
});

it('returns nothing for a user with no role in the scope', function () {
    $user = User::create(['name' => 'Frankie']);

    expect($user->visibleRoles($this->project))->toBeEmpty();
});

it('shows a system-role holder every non-system role in the scope', function () {
    $user = User::create(['name' => 'Root']);
    $user->assignRole($this->system);

    $names = $user->visibleRoles($this->project)->pluck('name')->all();

    expect($names)->toContain('owner', 'admin', 'member', 'viewer', 'reviewer')
        ->and($names)->not->toContain('system');
});
