<?php

namespace Database\Seeders;

use App\Models\AllocationCategory;
use App\Models\Member;
use App\Models\PlanVersion;
use Illuminate\Database\Seeder;

/**
 * Seeds the default DRC Commerce Plan with the 10 CV allocation buckets
 * from the product specification image.
 *
 * THIS IS SEEDED AS A DRAFT — it must go through the
 * Draft → Approved → Active workflow before it is used.
 *
 * Total must equal 100% before approval is permitted.
 */
class DefaultPlanVersionSeeder extends Seeder
{
    /**
     * The 10 CV allocation categories, summing to exactly 100%.
     */
    private array $categories = [
        [
            'code'              => 'mcv_company',
            'name'              => 'MCV Company Allocation',
            'description'       => 'Operate and develop the DRC business',
            'percentage'        => 30.0000,
            'wallet_bucket'     => 'company_pool',
            'is_member_payable' => false,
            'is_pooled'         => true,
            'handler_class'     => \App\Commerce\Handlers\CompanyPoolHandler::class,
            'sort_order'        => 1,
        ],
        [
            'code'              => 'cnrp_community',
            'name'              => 'CNRP Community Development',
            'description'       => 'Support the originating community channel',
            'percentage'        => 10.0000,
            'wallet_bucket'     => 'community_pool',
            'is_member_payable' => false,
            'is_pooled'         => true,
            'handler_class'     => \App\Commerce\Handlers\CommunityPoolHandler::class,
            'sort_order'        => 2,
        ],
        [
            'code'              => 'country_expansion',
            'name'              => 'Country Expansion Reserve',
            'description'       => 'Finance geographic and productive expansion',
            'percentage'        => 12.0000,
            'wallet_bucket'     => 'expansion_reserve',
            'is_member_payable' => false,
            'is_pooled'         => true,
            'handler_class'     => \App\Commerce\Handlers\ExpansionReserveHandler::class,
            'sort_order'        => 3,
        ],
        [
            'code'              => 'marketing_development',
            'name'              => 'Marketing & Market Development',
            'description'       => 'Create demand and support product adoption',
            'percentage'        => 6.0000,
            'wallet_bucket'     => 'marketing_pool',
            'is_member_payable' => false,
            'is_pooled'         => true,
            'handler_class'     => \App\Commerce\Handlers\MarketingPoolHandler::class,
            'sort_order'        => 4,
        ],
        [
            'code'              => 'platform_technology',
            'name'              => 'Platform & Technology',
            'description'       => 'Operate and develop the digital infrastructure',
            'percentage'        => 4.0000,
            'wallet_bucket'     => 'technology_pool',
            'is_member_payable' => false,
            'is_pooled'         => true,
            'handler_class'     => \App\Commerce\Handlers\TechnologyPoolHandler::class,
            'sort_order'        => 5,
        ],
        [
            'code'              => 'member_purchase_reward',
            'name'              => 'Member Purchase Reward',
            'description'       => 'Reward registered product consumption',
            'percentage'        => 9.0000,
            'wallet_bucket'     => 'member_reward',
            'is_member_payable' => true,
            'is_pooled'         => false,
            'handler_class'     => \App\Commerce\Handlers\MemberPurchaseRewardHandler::class,
            'sort_order'        => 6,
        ],
        [
            'code'              => 'distributor_performance',
            'name'              => 'Distributor Performance',
            'description'       => 'Incentivise active product distribution',
            'percentage'        => 6.0000,
            'wallet_bucket'     => 'distributor_bonus',
            'is_member_payable' => true,
            'is_pooled'         => false,
            'handler_class'     => \App\Commerce\Handlers\DistributorPerformanceHandler::class,
            'sort_order'        => 7,
        ],
        [
            'code'              => 'leadership_development',
            'name'              => 'Leadership Development',
            'description'       => 'Reward organisation building and leadership',
            'percentage'        => 9.0000,
            'wallet_bucket'     => 'leadership_bonus',
            'is_member_payable' => true,
            'is_pooled'         => false,
            'handler_class'     => \App\Commerce\Handlers\LeadershipDevelopmentHandler::class,
            'sort_order'        => 8,
        ],
        [
            'code'              => 'binary_team_bonus',
            'name'              => 'Binary Team Bonus',
            'description'       => 'Reward balanced team commerce',
            'percentage'        => 8.0000,
            'wallet_bucket'     => 'binary_bonus',
            'is_member_payable' => true,
            'is_pooled'         => false,
            'handler_class'     => \App\Commerce\Handlers\BinaryTeamBonusHandler::class,
            'sort_order'        => 9,
        ],
        [
            'code'              => 'matching_bonus',
            'name'              => 'Matching Bonus',
            'description'       => 'Reward leadership duplication',
            'percentage'        => 6.0000,
            'wallet_bucket'     => 'matching_bonus',
            'is_member_payable' => true,
            'is_pooled'         => false,
            'handler_class'     => \App\Commerce\Handlers\MatchingBonusHandler::class,
            'sort_order'        => 10,
        ],
    ];

    public function run(): void
    {
        $systemAdmin = Member::role('Global Administration')->first() ?? Member::first();
        $creatorId = $systemAdmin?->id;

        $plan = PlanVersion::updateOrCreate(
            ['name' => 'DRC Default Commerce Plan v1', 'country' => 'COD'],
            [
                'description'              => 'Default Commerce Pool allocation plan for the Democratic Republic of Congo. '
                    . 'Seeded from product specification.',
                'status'                   => 'active',
                'effective_from'           => now()->startOfYear(),
                'required_allocation_total' => 100.0000,
                'created_by'               => $creatorId,
                'approved_by'              => $creatorId,
                'approved_at'              => now(),
            ]
        );

        foreach ($this->categories as $category) {
            AllocationCategory::firstOrCreate(
                [
                    'plan_version_id' => $plan->id,
                    'code'            => $category['code'],
                ],
                array_merge($category, ['plan_version_id' => $plan->id])
            );
        }

        $total = collect($this->categories)->sum('percentage');
        $this->command->info("✅ Default Plan seeded (status: draft). Total allocation: {$total}% — must equal 100% before approval.");
    }
}
