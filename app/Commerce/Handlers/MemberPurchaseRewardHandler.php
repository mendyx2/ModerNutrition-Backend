<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * Member Purchase Reward — 9% CV
 * Rewarding registered product consumption directly to the purchasing member.
 */
class MemberPurchaseRewardHandler extends BaseAllocationHandler
{
    public function handle(Order $order, AllocationCategory $category, PlanVersion $planVersion): array
    {
        $amount = $this->calculateAmount($order, $category);
        if ($amount <= 0 || !$order->member_id) return [];

        $entry = $this->recordLedgerAndWallet(
            order: $order,
            category: $category,
            planVersion: $planVersion,
            memberId: $order->member_id,
            amount: $amount,
            description: "Member Purchase Reward (9% CV) on Order {$order->order_number}"
        );

        return [$entry];
    }
}
