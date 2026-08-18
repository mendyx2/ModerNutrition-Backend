<?php

namespace App\Commerce\Handlers;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

/**
 * Country Expansion Reserve — 12% CV
 * Pooled to finance geographic and productive expansion.
 */
class ExpansionReserveHandler extends BaseAllocationHandler
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
            description: "Country Expansion Reserve (12% CV) from Order {$order->order_number}"
        );

        return [$entry];
    }
}
