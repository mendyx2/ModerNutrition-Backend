<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * Matching Bonus — 6% CV
 * Rewarding leadership duplication across sponsor generations.
 */
class MatchingBonusHandler extends BaseAllocationHandler
{
    public function handle(Order $order, AllocationCategory $category, PlanVersion $planVersion): array
    {
        $amount = $this->calculateAmount($order, $category);
        if ($amount <= 0) return [];

        // Beneficiary is sponsor's upline sponsor (generation 2 mentor)
        $sponsor = $order->member?->sponsor;
        $beneficiaryId = $sponsor?->sponsor_id ?: $sponsor?->id ?: $order->member_id;

        $entry = $this->recordLedgerAndWallet(
            order: $order,
            category: $category,
            planVersion: $planVersion,
            memberId: $beneficiaryId,
            amount: $amount,
            description: "Matching Bonus (6% CV) on Order {$order->order_number} (Mentor duplication)"
        );

        return [$entry];
    }
}
