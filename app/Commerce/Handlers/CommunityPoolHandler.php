<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * CNRP Community Development — 10% CV
 * Pooled to support the originating community channel.
 */
class CommunityPoolHandler extends BaseAllocationHandler
{
    public function handle(Order $order, AllocationCategory $category, PlanVersion $planVersion): array
    {
        $amount = $this->calculateAmount($order, $category);
        if ($amount <= 0) return [];

        $entry = $this->recordLedgerAndWallet(
            order: $order,
            category: $category,
            planVersion: $planVersion,
            memberId: null,
            amount: $amount,
            description: "CNRP Community Development (10% CV) from Order {$order->order_number}"
        );

        return [$entry];
    }
}
