<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * Marketing & Market Development — 6% CV
 * Pooled to create demand and support product adoption.
 */
class MarketingPoolHandler extends BaseAllocationHandler
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
            description: "Marketing & Market Development (6% CV) from Order {$order->order_number}"
        );

        return [$entry];
    }
}
