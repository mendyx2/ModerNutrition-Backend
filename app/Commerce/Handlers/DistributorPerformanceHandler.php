<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * Distributor Performance — 6% CV
 * Incentivising active product distribution. Paid to the direct sponsor of the ordering member.
 */
class DistributorPerformanceHandler extends BaseAllocationHandler
{
    public function handle(Order $order, AllocationCategory $category, PlanVersion $planVersion): array
    {
        $amount = $this->calculateAmount($order, $category);
        if ($amount <= 0) return [];

        // Beneficiary is the purchasing member's sponsor, or fallback to the member if self-sponsored
        $sponsorId = $order->member?->sponsor_id ?: $order->member_id;

        $entry = $this->recordLedgerAndWallet(
            order: $order,
            category: $category,
            planVersion: $planVersion,
            memberId: $sponsorId,
            amount: $amount,
            description: "Distributor Performance (6% CV) on Order {$order->order_number} by Member #{$order->member?->member_number}"
        );

        return [$entry];
    }
}
