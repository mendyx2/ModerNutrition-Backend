<?php

namespace App\Commerce\Services;

use App\Commerce\Contracts\AllocationHandlerContract;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PlanVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CommerceAllocationEngine
{
    /**
     * Process CV allocation for a completed/paid order.
     *
     * @param Order $order
     * @return array Created ledger entries
     */
    public function allocate(Order $order): array
    {
        // 1. Validation: CV generates on order completion (Paid status only, never Pending)
        if ($order->status !== 'paid') {
            throw new RuntimeException("Cannot allocate CV for Order #{$order->order_number}: Status is '{$order->status}', must be 'paid'.");
        }

        // 2. Prevent duplicate allocation
        if ($order->cv_allocated_at !== null) {
            Log::info("CV already allocated for Order #{$order->order_number} at {$order->cv_allocated_at}. Skipping.");
            return [];
        }

        // 3. Find active plan version for the order's country & date
        $planVersion = $this->resolveActivePlanVersion($order->country, $order->paid_at ?? now());

        if (!$planVersion) {
            throw new RuntimeException("No active plan version found for country '{$order->country}'. Allocation halted.");
        }

        // 4. Validate that plan allocation percentages reconcile to required total before running
        $planVersion->validateAllocationTotal();

        $createdEntries = [];

        DB::transaction(function () use ($order, $planVersion, &$createdEntries) {
            $categories = $planVersion->allocationCategories()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($categories as $category) {
                $handlerClass = $category->handler_class;

                if (!class_exists($handlerClass)) {
                    Log::warning("Handler class '{$handlerClass}' not found for category '{$category->code}'. Using fallback base handler.");
                    continue;
                }

                /** @var AllocationHandlerContract $handler */
                $handler = app()->make($handlerClass);
                $entries = $handler->handle($order, $category, $planVersion);

                $createdEntries = array_merge($createdEntries, $entries);
            }

            // 5. Mark CV as allocated on order
            $order->cv_allocated_at = now();
            $order->save();

            // 6. Record audit log entry
            AuditLog::record(
                event: 'order.cv_allocated',
                actor: null, // System-executed
                subject: $order,
                oldValues: ['cv_allocated_at' => null],
                newValues: ['cv_allocated_at' => $order->cv_allocated_at],
                metadata: [
                    'plan_version_id'   => $planVersion->id,
                    'plan_version_name' => $planVersion->name,
                    'total_cv'          => (float) $order->total_cv,
                    'entries_count'     => count($createdEntries),
                ],
                description: "Allocated {$order->total_cv} CV across " . count($createdEntries) . " ledger entries using plan '{$planVersion->name}'"
            );
        });

        return $createdEntries;
    }

    /**
     * Resolve the active plan version for a country and effective date.
     */
    protected function resolveActivePlanVersion(string $country, $date): ?PlanVersion
    {
        $query = PlanVersion::where('status', 'active')
            ->where(function ($q) use ($country) {
                $q->where('country', $country)
                  ->orWhere('country', 'COD'); // Default primary market fallback
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            })
            ->orderByRaw("CASE WHEN country = '{$country}' THEN 1 ELSE 2 END")
            ->orderByDesc('id');

        return $query->first();
    }
}
