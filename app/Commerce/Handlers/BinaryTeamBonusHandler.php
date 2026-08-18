<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * Binary Team Bonus — 8% CV
 * Rewarding balanced team commerce across left and right binary legs.
 */
class BinaryTeamBonusHandler extends BaseAllocationHandler
{
    public function handle(Order $order, AllocationCategory $category, PlanVersion $planVersion): array
    {
        $amount = $this->calculateAmount($order, $category);
        if ($amount <= 0) return [];

        // Traverse binary parent tree to distribute balanced team credit
        $beneficiaryId = $order->member?->parent_id ?: $order->member?->sponsor_id ?: $order->member_id;

        $entry = $this->recordLedgerAndWallet(
            order: $order,
            category: $category,
            planVersion: $planVersion,
            memberId: $beneficiaryId,
            amount: $amount,
            description: "Binary Team Bonus (8% CV) on Order {$order->order_number} (Leg: {$order->member?->leg})"
        );

        return [$entry];
    }
}
