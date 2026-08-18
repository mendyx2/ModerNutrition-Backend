<?php

namespace App\Commerce\Contracts;

use App\Models\AllocationCategory;
use App\Models\Order;
use App\Models\PlanVersion;

interface AllocationHandlerContract
{
    /**
     * Handle the allocation calculation and ledger recording for this category.
     *
     * @param Order $order The paid triggering order
     * @param AllocationCategory $category The allocation bucket definition
     * @param PlanVersion $planVersion The active plan version at the time of the order
     * @return array Created ledger entry records
     */
    public function handle(Order $order, AllocationCategory $category, PlanVersion $planVersion): array;
}
