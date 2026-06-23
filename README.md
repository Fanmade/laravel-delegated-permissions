# Laravel Delegated Permissions

> Inheritance-based, scoped authorization for Laravel: each role delegates a
> subset of its own permissions to its children, and revoking cascades down the
> tree.

A Laravel authorization package built on **constrained role delegation**:

- One root **`system`** role per scope holds every permission; every other role
  descends from it (a single tree, one parent each).
- **A role may only hold permissions its parent holds** — it can't be granted,
  or even see, anything the parent lacks.
- **Revoking** a permission **cascades down** to every descendant that held it;
  **granting** never cascades.
- Roles, permissions and **permission groups** (CRUD-sets) are managed at
  runtime, gated by their own CRUD permissions.
- Roles **scope** to any model — per-project, per-team — or globally, with an
  optional **system scope above all others** for break-glass access.

## Status

Early development. See the design RFC and the build plan on the project board
(`KAN-232`, project `LDP`).

## Installation

```bash
composer require fanmade/laravel-delegated-permissions
```

Publish the config:

```bash
php artisan vendor:publish --tag=delegated-permissions-config
```

## License

MIT. See [LICENSE](LICENSE).
