# Changelog

All notable changes to `fanmade/laravel-delegated-permissions` are documented
here. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Configurable `max_roles_per_scope` cap on how many roles a single authorizable
  may hold within one scope (`1` = single role, `n` = up to n, `null`/`-1` =
  unlimited — the default). Assigning beyond the cap throws the new
  `RoleLimitExceeded` exception; effective permissions remain the union across
  every role held in the scope. The break-glass system role is exempt — never
  counted against the cap and never blocked by it — and re-assigning a role
  already held stays idempotent. Enforced in `PermissionResolver::assign()`, so
  it covers the `HasRoles::assignRole()` path too.

## [0.1.2] - 2026-06-25

### Changed

- `PermissionResolver` now memoises an authorizable's assigned roles per request,
  keyed by the authorizable alone. A role set does not vary by scope, so resolving
  the same authorizable across several scopes in one request no longer reloads it
  once per scope — it is loaded a single time and shared. `flush()` clears this
  cache alongside the existing per-scope resolution cache, so an assignment change
  is never served stale.

## [0.1.1] - 2026-06-23

### Changed

- `DelegatedPermissions::version()` now reads the installed version from
  Composer's runtime (`Composer\InstalledVersions`) instead of a hardcoded
  constant that drifted from the released tag. The `VERSION` constant is removed.

## [0.1.0] - 2026-06-23

### Added

- Constrained-delegation engine (`PermissionResolver`): the `system` role holds
  every permission, a role may only hold permissions its parent holds, revoking
  cascades to descendants, granting never does.
- Scoped role trees (polymorphic scope, or global) with a configurable system
  scope that sits above all others for break-glass access, toggleable via
  `DELEGATED_PERMISSIONS_SYSTEM_ENABLED`.
- `HasRoles` trait: `assignRole`, `removeRole`, `hasPermission`, `permissionsIn`,
  `hasRole`, `rolesIn`.
- Laravel Gate integration so `$user->can($permission, $scope)` routes through
  the resolver, with per-request resolution caching.
- Permission groups (CRUD-sets) with all-or-nothing delegation.
- `RoleManager` and `PermissionManager` for runtime role / permission / group
  management, plus the `ManagementPermission` enum.
- Configurable table prefix (`DELEGATED_PERMISSIONS_TABLE_PREFIX`).
- `php artisan ldp:install` command (variant selection, config publish, migrate,
  and seeding of the management permissions + a system role).
- Publishable Livewire UI boilerplate — role-tree manager, role-assignment panel,
  and permission/group catalog — in plain-Tailwind and Flux variants.
- Test suite covering SQLite and PostgreSQL.

[0.1.2]: https://github.com/Fanmade/laravel-delegated-permissions/releases/tag/v0.1.2
[0.1.0]: https://github.com/Fanmade/laravel-delegated-permissions/releases/tag/v0.1.0
