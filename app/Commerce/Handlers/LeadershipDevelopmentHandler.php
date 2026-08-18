<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Member;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * Leadership Development — 9% CV
 * Rewarding organisation building and leadership. Traverses sponsor lineage to allocate to qualified Leader+ rank upline.
 */
class LeadershipDevelopmentHandler extends BaseAllocationHandler
{
    public function handle(Order $order, AllocationCategory $category, PlanVersion $planVersion): array
    {
        $amount = $this->calculateAmount($order, $category);
        if ($amount <= 0) return [];

        // Find nearest qualified Leader (rank level >= 3) in sponsorship upline
        $leaderId = $this->findNearestQualifiedLeader($order->member);

        $entry = $this->recordLedgerAndWallet(
            order: $order,
            category: $category,
            planVersion: $planVersion,
            memberId: $leaderId,
            amount: $amount,
            description: "Leadership Development (9% CV) on Order {$order->order_number}"
        );

        return [$entry];
    }

    protected function findNearestQualifiedLeader(?Member $member): ?int
    {
        $current = $member?->sponsor;
        $depth = 0;
        $maxDepth = 20;

        while ($current && $depth < $maxDepth) {
            // Level 3+ = Leader or higher
            if ($current->currentRank && $current->currentRank->level >= 3) {
                return $current->id;
            }
            $current = $current->sponsor;
            $depth++;
        }

        // If no upline leader qualified, fallback to order sponsor or company pool fallback
        return $member?->sponsor_id ?: $member?->id;
    }
}
