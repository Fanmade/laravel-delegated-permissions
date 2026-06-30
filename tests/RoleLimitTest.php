<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\DelegatedPermissions;
use Fanmade\DelegatedPermissions\Exceptions\RoleLimitExceeded;
use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionResolver;
use Fanmade\DelegatedPermissions\Tests\Fixtures\Project;
use Fanmade\DelegatedPermissions\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::create(['name' => 'Apollo']);
    $this->other = Project::create(['name' => 'Gemini']);
    $this->system = Role::create(['name' => 'system', 'is_system' => true]);

    $scope = ['scope_type' => $this->project->getMorphClass(), 'scope_id' => $this->project->id];

    $this->owner = Role::create(['name' => 'owner', 'parent_id' => $this->system->id, ...$scope]);
    $this->member = Role::create(['name' => 'member', 'parent_id' => $this->owner->id, ...$scope]);
    $this->reviewer = Role::create(['name' => 'reviewer', 'parent_id' => $this->owner->id, ...$scope]);

    $this->otherMember = Role::create([
        'name' => 'member',
        'parent_id' => $this->system->id,
        'scope_type' => $this->other->getMorphClass(),
        'scope_id' => $this->other->id,
    ]);

    $this->user = User::create(['name' => 'Casey']);
});

it('allows multiple roles in one scope when unlimited (the default)', function () {
    $this->user->assignRole($this->member)->assignRole($this->reviewer);

    expect($this->user->rolesIn($this->project)->pluck('name')->sort()->values()->all())
        ->toBe(['member', 'reviewer']);
});

it('rejects a second role in the same scope once the cap is one', function () {
    config()->set('delegated-permissions.max_roles_per_scope', 1);

    $this->user->assignRole($this->member);

    expect(fn () => $this->user->assignRole($this->reviewer))
        ->toThrow(RoleLimitExceeded::class);

    expect($this->user->rolesIn($this->project)->pluck('name')->all())->toBe(['member']);
});

it('treats re-assigning a held role as idempotent at the cap', function () {
    config()->set('delegated-permissions.max_roles_per_scope', 1);

    $this->user->assignRole($this->member);

    expect(fn () => $this->user->assignRole($this->member))->not->toThrow(RoleLimitExceeded::class);
    expect($this->user->rolesIn($this->project)->pluck('name')->all())->toBe(['member']);
});

it('counts the cap per scope, not globally', function () {
    config()->set('delegated-permissions.max_roles_per_scope', 1);

    // One role in each of two scopes is fine — the cap is per scope.
    $this->user->assignRole($this->member)->assignRole($this->otherMember);

    expect($this->user->rolesIn($this->project)->pluck('name')->all())->toBe(['member'])
        ->and($this->user->rolesIn($this->other)->pluck('name')->all())->toBe(['member']);
});

it('honours a cap greater than one', function () {
    config()->set('delegated-permissions.max_roles_per_scope', 2);

    $this->user->assignRole($this->member)->assignRole($this->reviewer);

    expect(fn () => $this->user->assignRole($this->owner))
        ->toThrow(RoleLimitExceeded::class);
});

it('never counts or blocks the exempt system role', function () {
    config()->set('delegated-permissions.max_roles_per_scope', 1);

    // The system role does not occupy the scope's single slot...
    $this->user->assignRole($this->system)->assignRole($this->member);

    // ...and may itself be assigned even when a scoped role already fills the cap.
    expect(fn () => $this->user->assignRole($this->system))->not->toThrow(RoleLimitExceeded::class);

    expect($this->user->rolesIn($this->project)->pluck('name')->all())->toBe(['member']);
});

it('reads assignments freshly so a stale role memo cannot bypass the cap', function () {
    config()->set('delegated-permissions.max_roles_per_scope', 1);

    $resolver = app(PermissionResolver::class);

    // Warm the per-request role memo while the user still holds nothing.
    $resolver->assignedRoles($this->user);

    // A first scoped role is committed out-of-band, as a concurrent request
    // would — the warmed (empty) memo does not see it.
    DB::table(DelegatedPermissions::table('role_assignments'))->insert([
        'role_id' => $this->member->id,
        'authorizable_type' => $this->user->getMorphClass(),
        'authorizable_id' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The scope's single slot is now full; the guard must re-read committed
    // state and reject the second role rather than trusting the stale memo.
    expect(fn () => $resolver->assign($this->user, $this->reviewer))
        ->toThrow(RoleLimitExceeded::class);
});

it('treats null and a negative cap as unlimited', function () {
    config()->set('delegated-permissions.max_roles_per_scope', -1);

    $this->user->assignRole($this->member)->assignRole($this->reviewer)->assignRole($this->owner);

    expect($this->user->rolesIn($this->project))->toHaveCount(3);
});
