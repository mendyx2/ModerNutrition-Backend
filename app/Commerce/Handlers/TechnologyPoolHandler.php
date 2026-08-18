<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * Platform & Technology — 4% CV
 * Pooled to operate and develop the digital infrastructure.
 */
class TechnologyPoolHandler extends BaseAllocationHandler
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
            description: "Platform & Technology (4% CV) from Order {$order->order_number}"
        );

        return [$entry];
    }
}
