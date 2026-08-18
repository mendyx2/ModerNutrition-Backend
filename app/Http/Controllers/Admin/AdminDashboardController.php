<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     * Commerce Pool balance, total distributed, pending actions count.
     */
    public function summary(Request $request): JsonResponse
    {
        // 1. Total CV generated across all paid orders
        $totalCvGenerated = (float) Order::where('status', 'paid')->sum('total_cv');

        // 2. Total Distributed across member bonuses (Member-payable categories)
        $totalDistributed = (float) LedgerEntry::whereNotNull('member_id')
            ->where('entry_type', 'cv_allocation')
            ->sum('amount');

        // 3. Commerce Pool Balance (Company & Pooled buckets)
        $commercePoolBalance = (float) LedgerEntry::whereNull('member_id')
            ->where('entry_type', 'cv_allocation')
            ->sum('amount');

        // 4. Pending Actions Count (Maker-checker withdrawals pending + pending member approvals)
        $pendingWithdrawals = Withdrawal::where('status', 'pending')->count();
        $pendingMembers = Member::where('status', 'pending')->count();
        $pendingOrders = Order::where('status', 'pending')->count();

        $totalMembers = Member::count();
        $activeMembers = Member::where('status', 'active')->count();

        return response()->json([
            'commerce_pool_balance' => $commercePoolBalance,
            'total_distributed'     => $totalDistributed,
            'total_cv_generated'    => $totalCvGenerated,
            'pending_actions' => [
                'total'               => $pendingWithdrawals + $pendingMembers + $pendingOrders,
                'pending_withdrawals' => $pendingWithdrawals,
                'pending_members'     => $pendingMembers,
                'pending_orders'      => $pendingOrders,
            ],
            'member_stats' => [
                'total'  => $totalMembers,
                'active' => $activeMembers,
            ],
        ]);
    }
}
