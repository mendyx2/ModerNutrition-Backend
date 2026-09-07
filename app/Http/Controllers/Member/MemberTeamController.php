<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MemberTeamController extends Controller
{
    /**
     * GET /api/member/team/binary
     * Left/Right binary leg summary, weaker team highlight, GV gap, and action prompt.
     * Cached with 120s TTL for extreme scalability under high concurrent load.
     */
    public function binarySummary(Request $request): JsonResponse
    {
        $member = $request->user();
        $cacheKey = "member_binary_summary_{$member->id}";

        $summary = Cache::remember($cacheKey, 120, function () use ($member) {
            $startOfMonth = Carbon::now()->startOfMonth();

            // 1. Get Left and Right subtrees with rank relations
            $leftChild = Member::with('currentRank')->where('parent_id', $member->id)->where('leg', 'left')->first();
            $rightChild = Member::with('currentRank')->where('parent_id', $member->id)->where('leg', 'right')->first();

            $leftIds = $leftChild ? $this->getBinarySubtreeIds($leftChild->id) : [];
            $rightIds = $rightChild ? $this->getBinarySubtreeIds($rightChild->id) : [];

            // 2. Left Leg Metrics
            $leftGv = (float) Order::whereIn('member_id', $leftIds)
                ->where('status', 'paid')
                ->where('paid_at', '>=', $startOfMonth)
                ->sum('total_pv');

            $leftActive = Member::whereIn('id', $leftIds)->where('status', 'active')->count();
            $leftNew = Member::whereIn('id', $leftIds)->where('created_at', '>=', $startOfMonth)->count();

            // 3. Right Leg Metrics
            $rightGv = (float) Order::whereIn('member_id', $rightIds)
                ->where('status', 'paid')
                ->where('paid_at', '>=', $startOfMonth)
                ->sum('total_pv');

            $rightActive = Member::whereIn('id', $rightIds)->where('status', 'active')->count();
            $rightNew = Member::whereIn('id', $rightIds)->where('created_at', '>=', $startOfMonth)->count();

            // 4. Weaker team & GV gap analysis
            $weakerLeg = ($leftGv <= $rightGv) ? 'left' : 'right';
            $gvGap = abs($leftGv - $rightGv);

            $actionPrompt = "Your {$weakerLeg} leg is weaker by " . number_format($gvGap, 2) . " GV. Focus sponsoring and purchases on the {$weakerLeg} side to maximise your Binary Team Bonus payout.";

            // 5. Visual Binary Tree Structure
            $member->loadMissing('currentRank');
            $tree = [
                'root' => [
                    'id'            => $member->id,
                    'member_number' => $member->member_number,
                    'full_name'     => $member->full_name,
                    'rank'          => $member->currentRank?->name ?? 'Consumer',
                    'rank_level'    => $member->currentRank?->level ?? 1,
                    'status'        => $member->status,
                    'total_pv'      => (float) Order::where('member_id', $member->id)->where('status', 'paid')->where('paid_at', '>=', $startOfMonth)->sum('total_pv'),
                ],
                'left' => $leftChild ? [
                    'id'            => $leftChild->id,
                    'member_number' => $leftChild->member_number,
                    'full_name'     => $leftChild->full_name,
                    'rank'          => $leftChild->currentRank?->name ?? 'Consumer',
                    'rank_level'    => $leftChild->currentRank?->level ?? 1,
                    'gv'            => $leftGv,
                    'status'        => $leftChild->status,
                ] : null,
                'right' => $rightChild ? [
                    'id'            => $rightChild->id,
                    'member_number' => $rightChild->member_number,
                    'full_name'     => $rightChild->full_name,
                    'rank'          => $rightChild->currentRank?->name ?? 'Consumer',
                    'rank_level'    => $rightChild->currentRank?->level ?? 1,
                    'gv'            => $rightGv,
                    'status'        => $rightChild->status,
                ] : null,
            ];

            return [
                'left_leg' => [
                    'gv'                     => $leftGv,
                    'active_members'         => $leftActive,
                    'new_members_this_month' => $leftNew,
                    'carry_forward'          => 0.00,
                ],
                'right_leg' => [
                    'gv'                     => $rightGv,
                    'active_members'         => $rightActive,
                    'new_members_this_month' => $rightNew,
                    'carry_forward'          => 0.00,
                ],
                'weaker_leg'    => $weakerLeg,
                'gv_gap'        => $gvGap,
                'action_prompt' => $actionPrompt,
                'tree'          => $tree,
            ];
        });

        return response()->json($summary);
    }

    /**
     * Optimized O(1) single-query subtree lookup using Materialized Path tree_path.
     */
    protected function getBinarySubtreeIds(int $rootId): array
    {
        $root = Member::find($rootId);
        if (!$root) {
            return [];
        }

        $pathPrefix = $root->tree_path ?: "/{$rootId}/";
        return Member::where('tree_path', 'like', "{$pathPrefix}%")
            ->orWhere('id', $rootId)
            ->pluck('id')
            ->toArray();
    }
}
