<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    | Default guard: sanctum (token-based for all API consumers).
    | Both ModerNutrition-Dev and ModerNutrition-Admin authenticate here.
    | Roles/permissions are scoped per-token via Spatie HasRoles on Member.
    */
    'defaults' => [
        'guard'     => 'sanctum',
        'passwords' => 'members',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'members',
        ],
        // No separate admin guard — role-based scoping on a single guard.
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    | Maps to the Member model (not the default Laravel User model).
    */
    'providers' => [
        'members' => [
            'driver' => 'eloquent',
            'model'  => env('AUTH_MODEL', \App\Models\Member::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset
    |--------------------------------------------------------------------------
    */
    'passwords' => [
        'members' => [
            'provider' => 'members',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
