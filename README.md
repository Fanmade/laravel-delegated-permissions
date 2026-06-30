# Laravel Delegated Permissions

> Inheritance-based, scoped authorization for Laravel: each role delegates a
> subset of its own permissions to its children, and revoking cascades down the
> tree.

Most permission packages give you flat roles and permissions. This one is built
around **constrained delegation** — a single tree per scope where a parent role
can only hand its children a *subset* of what it holds, revocation flows
downward, and roles are scoped to any model (per-project, per-team) or global.

## The model

- **One `system` role** roots every scope's tree. It implicitly holds *every*
  permission and is the only role without a parent.
- **A role may only hold permissions its parent holds.** A child can't be
  granted — or even see — a permission its parent lacks.
- **Revoking cascades down.** Removing a permission from a role removes it from
  every descendant that still had it.
- **Granting never cascades.** Adding a permission to a role leaves its children
  untouched — you delegate downward deliberately.
- **Scopes.** Roles belong to a scope (a model such as a `Project`, or `null`
  for the global scope). An optional **system scope sits above all others** as
  break-glass access — disable it once setup is done.
- **Permission groups** bundle permissions (e.g. a `tags` CRUD-set) for
  convenient, all-or-nothing delegation.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

```bash
composer require fanmade/laravel-delegated-permissions
```

The migrations load automatically. Optionally publish the config and migrations:

```bash
php artisan vendor:publish --tag=delegated-permissions-config
php artisan vendor:publish --tag=delegated-permissions-migrations
php artisan migrate
```

## Configuration

All settings live in `config/delegated-permissions.php` and read from the
environment:

| Env var | Default | Purpose |
| --- | --- | --- |
| `DELEGATED_PERMISSIONS_TABLE_PREFIX` | `''` | Prefix for every package table, to avoid clashes. |
| `DELEGATED_PERMISSIONS_SYSTEM_ENABLED` | `true` | Master switch for the break-glass system role. Turn it **off** after setup. |
| `DELEGATED_PERMISSIONS_REGISTER_GATE` | `true` | Route `$user->can(...)` through the resolver. |

The system role's `scope_above_all` (whether it reaches every scope) is set in
the config file.

## The authorizable

Add the `HasRoles` trait to the model that holds roles — usually your `User`:

```php
use Fanmade\DelegatedPermissions\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

You now have `assignRole()`, `removeRole()`, `roles()`, `hasPermission()`,
`permissionsIn()`, `hasRole()` and `rolesIn()`.

## Building a tree

Use the managers (resolve them from the container) to create permissions, a
system root, and delegated child roles. Out-of-bounds grants are rejected.

```php
use Fanmade\DelegatedPermissions\{PermissionManager, RoleManager};

$permissions = app(PermissionManager::class);
$roles = app(RoleManager::class);

// Catalog
foreach (['view-project', 'manage-tags', 'delete-tasks'] as $name) {
    $permissions->createPermission($name);
}

// A project's tree: system → owner → member, scoped to the project.
$system = $roles->createSystemRole();                 // global root, holds everything
$owner  = $roles->createRole('owner',  $system, ['view-project', 'manage-tags', 'delete-tasks'], $project);
$member = $roles->createRole('member', $owner,  ['view-project']); // scope inherited from the owner

// Granting beyond the parent is rejected:
$roles->createRole('intern', $member, ['manage-tags']); // throws OutOfBoundsGrant — member lacks it
```

Assign roles and check permissions, scoped to the project:

```php
$user->assignRole($member);

$user->hasPermission('view-project', $project); // true
$user->hasPermission('manage-tags', $project);  // false
$user->can('view-project', $project);           // true — via the Gate integration
```

## Scopes

Roles are isolated per scope. A role in project A grants nothing in project B:

```php
$user->assignRole($ownerOfProjectA);

$user->hasPermission('manage-tags', $projectA); // true
$user->hasPermission('manage-tags', $projectB); // false
$user->permissionsIn(null);                     // global-scope permissions only
```

## Roles per scope

A model may hold several roles within one scope; effective permissions are the
**union** across them. The `max_roles_per_scope` config caps how many — `1` for
the classic single-role model, `n` for up to n, `null`/`-1` (the default) for
unlimited:

```php
// config/delegated-permissions.php → 'max_roles_per_scope' => 1
$user->assignRole($designer);             // ok — first role in the scope
$user->assignRole($reviewer);             // throws RoleLimitExceeded
```

The cap is counted per scope, so a role in another project is unaffected.
Re-assigning a role the model already holds is idempotent and never trips the
cap, and the break-glass system role is exempt — it is neither counted nor
blocked. Catch `RoleLimitExceeded` if you want to evict-then-assign instead of
rejecting.

## The system role (break-glass)

The `system` role implicitly holds everything and, with `scope_above_all` on,
reaches every scope — intended for initial setup and emergency fixes, **not**
routine use. Disable it afterward and it grants nothing:

```php
$admin->assignRole($systemRole);
$admin->hasPermission('anything', $anyProject); // true while enabled

// .env: DELEGATED_PERMISSIONS_SYSTEM_ENABLED=false
$admin->hasPermission('anything', $anyProject); // false
```

## Permission groups

Bundle permissions and delegate them as a unit. A group can only be granted if
the parent holds *every* permission in it (all-or-nothing); individual
permissions can still be revoked afterward:

```php
$permissions->createGroup('tags', ['manage-tags', 'delete-tags', 'create-tags']);

app(PermissionResolver::class)->grantGroup($adminRole, 'tags');
app(PermissionResolver::class)->revoke($adminRole, 'delete-tags'); // prunes just one
```

## Managing the package itself

The CRUD operations are gated by the package's own permissions — see
`Fanmade\DelegatedPermissions\ManagementPermission` (`create-roles`,
`delete-permissions`, `assign-roles`, …). Seed them with
`app(PermissionManager::class)->installManagementPermissions()`, grant them to an
admin role, and gate your management UI with `$user->can('create-roles')`.

## Testing

The suite runs on SQLite and PostgreSQL:

```bash
vendor/bin/pest                                   # SQLite (in-memory)
DB_CONNECTION=pgsql DB_DATABASE=testing \
  DB_USERNAME=postgres DB_PASSWORD=postgres \
  vendor/bin/pest                                 # PostgreSQL
```

## License

MIT. See [LICENSE](LICENSE).
