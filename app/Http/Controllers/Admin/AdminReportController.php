<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    /**
     * GET /api/admin/reports/daily-sales
     */
    public function dailySales(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $since = Carbon::now()->subDays($days);

        $sales = Order::where('status', 'paid')
            ->where('paid_at', '>=', $since)
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_cents) as total_sales_cents'),
                DB::raw('SUM(total_pv) as total_pv'),
                DB::raw('SUM(total_cv) as total_cv')
            )
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->orderBy('date')
            ->get();

        return response()->json(['daily_sales' => $sales]);
    }

    /**
     * GET /api/admin/reports/country-sales
     */
    public function countrySales(Request $request): JsonResponse
    {
        $countrySales = Order::where('status', 'paid')
            ->select(
                'country',
                'currency',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_cents) as total_sales_cents'),
                DB::raw('SUM(total_pv) as total_pv'),
                DB::raw('SUM(total_cv) as total_cv')
            )
            ->groupBy('country', 'currency')
            ->get();

        return response()->json(['country_sales' => $countrySales]);
    }

    /**
     * GET /api/admin/reports/product-performance
     */
    public function productPerformance(Request $request): JsonResponse
    {
        $products = OrderItem::select(
                'product_sku',
                'product_name',
                DB::raw('SUM(quantity) as total_units_sold'),
                DB::raw('SUM(line_total_cents) as total_revenue_cents'),
                DB::raw('SUM(line_pv) as total_pv_generated'),
                DB::raw('SUM(line_cv) as total_cv_generated')
            )
            ->whereHas('order', function ($q) {
                $q->where('status', 'paid');
            })
            ->groupBy('product_sku', 'product_name')
            ->orderByDesc('total_units_sold')
            ->get();

        return response()->json(['product_performance' => $products]);
    }

    /**
     * GET /api/admin/reports/commerce-pool
     */
    public function commercePool(Request $request): JsonResponse
    {
        $poolSummary = LedgerEntry::with('allocationCategory:id,name,percentage,wallet_bucket,is_pooled')
            ->select(
                'allocation_category_id',
                'currency',
                DB::raw('SUM(amount) as total_allocated_amount'),
                DB::raw('COUNT(*) as entries_count')
            )
            ->groupBy('allocation_category_id', 'currency')
            ->get();

        return response()->json(['commerce_pool_summary' => $poolSummary]);
    }

    /**
     * GET /api/admin/reports/bonus-distribution
     */
    public function bonusDistribution(Request $request): JsonResponse
    {
        $bonuses = LedgerEntry::whereNotNull('member_id')
            ->where('entry_type', 'cv_allocation')
            ->with('allocationCategory:id,name,wallet_bucket')
            ->select(
                'allocation_category_id',
                DB::raw('SUM(amount) as total_bonus_amount'),
                DB::raw('COUNT(DISTINCT member_id) as distinct_beneficiaries_count')
            )
            ->groupBy('allocation_category_id')
            ->get();

        return response()->json(['bonus_distribution' => $bonuses]);
    }

    /**
     * GET /api/admin/reports/member-activity
     */
    public function memberActivity(Request $request): JsonResponse
    {
        $totalMembers = Member::count();
        $activeMembers = Member::where('status', 'active')->count();
        $suspendedMembers = Member::where('status', 'suspended')->count();
        $newThisMonth = Member::where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        $byRank = Member::select('current_rank_id', DB::raw('COUNT(*) as count'))
            ->with('currentRank:id,name,level')
            ->groupBy('current_rank_id')
            ->get();

        return response()->json([
            'total_members'     => $totalMembers,
            'active_members'    => $activeMembers,
            'suspended_members' => $suspendedMembers,
            'new_this_month'    => $newThisMonth,
            'by_rank'           => $byRank,
        ]);
    }
}
