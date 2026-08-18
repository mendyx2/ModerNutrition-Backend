<?php

namespace App\Commerce\Handlers;

use App\Commerce\Contracts\AllocationHandlerContract;
use App\Models\AllocationCategory;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PlanVersion;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

abstract class BaseAllocationHandler implements AllocationHandlerContract
{
    /**
     * Calculate the raw currency amount for this category given the order's total CV.
     * amount = (order.total_cv * category.percentage / 100)
     */
    protected function calculateAmount(Order $order, AllocationCategory $category): float
    {
        $cvBasis = (float) $order->total_cv;
        $pct = (float) $category->percentage;

        return round(($cvBasis * $pct) / 100, 4);
    }

    /**
     * Record an append-only ledger entry and update the wallet read-model.
     */
    protected function recordLedgerAndWallet(
        Order $order,
        AllocationCategory $category,
        PlanVersion $planVersion,
        ?int $memberId,
        float $amount,
        string $description
    ): LedgerEntry {
        return DB::transaction(function () use ($order, $category, $planVersion, $memberId, $amount, $description) {
            // 1. Calculate running balance for this member / category if memberId present
            $currentBalance = 0.0;
            if ($memberId) {
                $lastEntry = LedgerEntry::where('member_id', $memberId)
                    ->where('allocation_category_id', $category->id)
                    ->latest('id')
                    ->first();

                $currentBalance = $lastEntry ? (float) $lastEntry->running_balance : 0.0;
            }

            $newRunningBalance = $currentBalance + $amount;

            // 2. Append-only ledger record (NEVER updated)
            $entry = LedgerEntry::record([
                'entry_type'             => 'cv_allocation',
                'reference_type'         => Order::class,
                'reference_id'           => $order->id,
                'member_id'              => $memberId,
                'allocation_category_id' => $category->id,
                'plan_version_id'        => $planVersion->id,
                'currency'               => $order->currency,
                'country'                => $order->country,
                'amount'                 => $amount,
                'cv_basis'               => $order->total_cv,
                'percentage_applied'     => $category->percentage,
                'running_balance'        => $newRunningBalance,
                'description'            => $description,
                'is_reversal'            => false,
            ]);

            // 3. Update materialised Wallet read-model for member
            if ($memberId) {
                $wallet = Wallet::firstOrCreate(
                    [
                        'member_id'     => $memberId,
                        'wallet_bucket' => $category->wallet_bucket,
                        'currency'      => $order->currency,
                    ],
                    [
                        'allocation_category_id' => $category->id,
                        'country'                => $order->country,
                        'total_earned'           => 0,
                        'total_withdrawn'        => 0,
                        'total_reversed'         => 0,
                        'available_balance'      => 0,
                        'pending_withdrawal'     => 0,
                    ]
                );

                $wallet->total_earned = (float) $wallet->total_earned + $amount;
                $wallet->available_balance = (float) $wallet->available_balance + $amount;
                $wallet->last_ledger_entry_at = now();
                $wallet->save();
            }

            return $entry;
        });
    }
}
