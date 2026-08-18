<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Permission / Role Model
    |--------------------------------------------------------------------------
    */
    'models' => [
        'permission' => Spatie\LaravelPermission\Models\Permission::class,
        'role'       => Spatie\LaravelPermission\Models\Role::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'table_names' => [
        'roles'                 => 'roles',
        'permissions'           => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles'       => 'model_has_roles',
        'role_has_permissions'  => 'role_has_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    */
    'column_names' => [
        'role_pivot_key'       => null, // e.g. 'role_id'
        'permission_pivot_key' => null, // e.g. 'permission_id'
        'model_morph_key'      => 'model_id',
        'team_foreign_key'     => 'team_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Register Permission Check Method
    |--------------------------------------------------------------------------
    | Registers `can` and `hasPermissionTo` macros on the User/Member model.
    */
    'register_permission_check_method' => true,

    /*
    |--------------------------------------------------------------------------
    | Register Octane Reset Listener
    |--------------------------------------------------------------------------
    */
    'register_octane_reset_listener' => false,

    /*
    |--------------------------------------------------------------------------
    | Teams Feature
    |--------------------------------------------------------------------------
    | Disabled — we use country-scoped permissions rather than teams.
    */
    'teams' => false,
    'use_passport_client_credentials' => false,

    /*
    |--------------------------------------------------------------------------
    | Display Permission In Exception
    |--------------------------------------------------------------------------
    */
    'display_permission_in_exception' => false,
    'display_role_in_exception'       => false,

    /*
    |--------------------------------------------------------------------------
    | Enable Wildcard Permission
    |--------------------------------------------------------------------------
    */
    'enable_wildcard_permission' => false,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'expiration_time'  => \DateInterval::createFromDateString('24 hours'),
        'key'              => 'spatie.permission.cache',
        'store'            => 'redis',
    ],
];
