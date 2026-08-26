<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Rank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $globalRank = Rank::where('slug', 'global_administration')->first();
        $countryRank = Rank::where('slug', 'country_operations')->first();

        // 1. Global Administration User
        $globalAdmin = Member::firstOrCreate(
            ['email' => 'admin@modernutrition.cd'],
            [
                'member_number' => 'MN-ADM-001',
                'first_name'    => 'Global',
                'last_name'     => 'Administrator',
                'password'      => Hash::make('Admin12345!'),
                'country'       => 'COD',
                'currency'      => 'USD',
                'city'          => 'Kinshasa',
                'address'       => 'Boulevard du 30 Juin, Gombe',
                'phone'         => '+243 81 000 0001',
                'current_rank_id' => $globalRank?->id,
                'status'        => 'active',
            ]
        );
        $globalAdmin->syncRoles(['Global Administration']);

        // 2. Country Operations User
        $countryOps = Member::firstOrCreate(
            ['email' => 'ops@modernutrition.cd'],
            [
                'member_number' => 'MN-OPS-001',
                'first_name'    => 'Country',
                'last_name'     => 'Operations',
                'password'      => Hash::make('Admin12345!'),
                'country'       => 'COD',
                'currency'      => 'USD',
                'city'          => 'Kinshasa',
                'address'       => 'Avenue de la Paix, Gombe',
                'phone'         => '+243 81 000 0002',
                'current_rank_id' => $countryRank?->id,
                'status'        => 'active',
            ]
        );
        $countryOps->syncRoles(['Country Operations']);

        $this->command->info('✅ Default Admin & Operations accounts seeded successfully.');
    }
}
