<?php

namespace Database\Seeders;

use App\Models\Rank;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The five additive roles, ordered by level.
     * A member holds all roles up to and including their current level.
     */
    private array $roles = [
        ['name' => 'Consumer',              'slug' => 'consumer',              'level' => 1],
        ['name' => 'Distributor',           'slug' => 'distributor',           'level' => 2],
        ['name' => 'Leader',                'slug' => 'leader',                'level' => 3],
        ['name' => 'Country Operations',    'slug' => 'country_operations',    'level' => 4],
        ['name' => 'Global Administration', 'slug' => 'global_administration', 'level' => 5],
    ];

    /**
     * Granular permissions grouped by domain.
     * Role-permission assignments are at the bottom of this seeder.
     */
    private array $permissions = [
        // --- Auth ---
        'auth.login', 'auth.logout', 'auth.register',

        // --- Profile ---
        'profile.view', 'profile.update',

        // --- Products ---
        'products.view-public',    // Public catalogue — no auth needed but still scoped
        'products.view',
        'products.create',
        'products.update',
        'products.delete',

        // --- Orders ---
        'orders.view-own',
        'orders.create',
        'orders.view-all',
        'orders.update-status',

        // --- Members ---
        'members.view-own',
        'members.view-all',
        'members.approve',
        'members.suspend',
        'members.rank-override',
        'members.view-team',

        // --- Commerce Engine ---
        'plan-versions.view',
        'plan-versions.create',
        'plan-versions.update',
        'plan-versions.approve',
        'plan-versions.activate',
        'allocation-categories.view',
        'allocation-categories.create',
        'allocation-categories.update',
        'allocation-categories.delete',

        // --- Wallets ---
        'wallets.view-own',
        'wallets.view-all',

        // --- Withdrawals ---
        'withdrawals.request',
        'withdrawals.view-own',
        'withdrawals.view-all',
        'withdrawals.approve',     // Maker-checker: approver must differ from requester
        'withdrawals.reject',

        // --- Marketing Assets ---
        'marketing-assets.view',
        'marketing-assets.create',
        'marketing-assets.update',
        'marketing-assets.delete',

        // --- Reports ---
        'reports.view-own',        // Member views their own activity
        'reports.view-country',    // Country Operations can see country-level reports
        'reports.view-global',     // Global Administration only

        // --- Audit Log ---
        'audit-log.view',

        // --- Dashboard ---
        'dashboard.member',
        'dashboard.admin',
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Create roles and assign permissions
        $consumer = Role::firstOrCreate(['name' => 'Consumer', 'guard_name' => 'sanctum']);
        $consumer->syncPermissions([
            'auth.login', 'auth.logout',
            'profile.view', 'profile.update',
            'products.view-public', 'products.view',
            'orders.view-own', 'orders.create',
            'members.view-own', 'members.view-team',
            'wallets.view-own',
            'withdrawals.view-own',
            'marketing-assets.view',
            'reports.view-own',
            'dashboard.member',
        ]);

        $distributor = Role::firstOrCreate(['name' => 'Distributor', 'guard_name' => 'sanctum']);
        $distributor->syncPermissions([
            // inherits all Consumer permissions plus:
            ...$consumer->permissions->pluck('name')->toArray(),
            'withdrawals.request',
        ]);

        $leader = Role::firstOrCreate(['name' => 'Leader', 'guard_name' => 'sanctum']);
        $leader->syncPermissions([
            ...$distributor->permissions->pluck('name')->toArray(),
            // Leaders can see their downline reports
            'reports.view-own',
        ]);

        $countryOps = Role::firstOrCreate(['name' => 'Country Operations', 'guard_name' => 'sanctum']);
        $countryOps->syncPermissions([
            ...$leader->permissions->pluck('name')->toArray(),
            // Country Operations admin capabilities
            'members.view-all', 'members.approve', 'members.suspend',
            'orders.view-all', 'orders.update-status',
            'products.create', 'products.update',
            'plan-versions.view', 'plan-versions.create', 'plan-versions.update',
            'allocation-categories.view', 'allocation-categories.create', 'allocation-categories.update',
            'wallets.view-all',
            'withdrawals.view-all', 'withdrawals.approve', 'withdrawals.reject',
            'marketing-assets.create', 'marketing-assets.update',
            'reports.view-country',
            'audit-log.view',
            'dashboard.admin',
        ]);

        $globalAdmin = Role::firstOrCreate(['name' => 'Global Administration', 'guard_name' => 'sanctum']);
        $globalAdmin->syncPermissions([
            ...$countryOps->permissions->pluck('name')->toArray(),
            // Full platform access
            'products.delete',
            'members.rank-override',
            'plan-versions.approve', 'plan-versions.activate',
            'allocation-categories.delete',
            'marketing-assets.delete',
            'reports.view-global',
        ]);

        // ------------------------------------------------------------------
        // Seed Ranks (mirrors the 5 roles)
        // ------------------------------------------------------------------
        $rankData = [
            ['name' => 'Consumer',              'slug' => 'consumer',              'level' => 1, 'description' => 'Registered product consumer.'],
            ['name' => 'Distributor',           'slug' => 'distributor',           'level' => 2, 'description' => 'Active product distributor.'],
            ['name' => 'Leader',                'slug' => 'leader',                'level' => 3, 'description' => 'Organisation builder and team leader.'],
            ['name' => 'Country Operations',    'slug' => 'country_operations',    'level' => 4, 'description' => 'Country-level operational administrator.'],
            ['name' => 'Global Administration', 'slug' => 'global_administration', 'level' => 5, 'description' => 'Global platform administrator.'],
        ];

        foreach ($rankData as $data) {
            Rank::firstOrCreate(['slug' => $data['slug']], $data);
        }

        $this->command->info('✅ Roles, permissions, and ranks seeded successfully.');
    }
}
