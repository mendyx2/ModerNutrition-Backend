<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberTeamController extends Controller
{
    /**
     * GET /api/member/team/binary
     * Left/Right binary leg summary, weaker team highlight, GV gap, and action prompt.
     */
    public function binarySummary(Request $request): JsonResponse
    {
        $member = $request->user();
        $startOfMonth = Carbon::now()->startOfMonth();

        // 1. Get Left and Right subtrees
        $leftChild = Member::where('parent_id', $member->id)->where('leg', 'left')->first();
        $rightChild = Member::where('parent_id', $member->id)->where('leg', 'right')->first();

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

        return response()->json([
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
        ]);
    }

    protected function getBinarySubtreeIds(int $rootId, int $maxDepth = 15): array
    {
        $allIds = [$rootId];
        $currentLevel = [$rootId];

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            if (empty($currentLevel)) break;
            $nextLevel = Member::whereIn('parent_id', $currentLevel)->pluck('id')->toArray();
            $allIds = array_merge($allIds, $nextLevel);
            $currentLevel = $nextLevel;
        }

        return array_unique($allIds);
    }
}
