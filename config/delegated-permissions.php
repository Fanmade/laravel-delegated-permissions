<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | The database tables backing the package. Fleshed out as the schema lands
    | (see LDP-2); listed here so host apps can rename them up front.
    |
    */

    'tables' => [
        'permissions' => 'permissions',
        'permission_groups' => 'permission_groups',
        'roles' => 'roles',
        'permission_role' => 'permission_role',
        'role_assignments' => 'role_assignments',
    ],

    /*
    |--------------------------------------------------------------------------
    | System role
    |--------------------------------------------------------------------------
    |
    | The root role of every scope's tree. It implicitly holds every permission
    | and is the only role without a parent.
    |
    */

    'system_role' => 'system',

    /*
    |--------------------------------------------------------------------------
    | System scope above all
    |--------------------------------------------------------------------------
    |
    | When true, the global "system" scope sits above every other scope, so a
    | system role can reach any scope by default — intended as break-glass
    | access (initial setup, critical fixes), not routine use. Set false to keep
    | scopes fully isolated.
    |
    */

    'system_scope_above_all' => true,

];
