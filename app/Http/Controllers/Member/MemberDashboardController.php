<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\QualificationRule;
use App\Models\Rank;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberDashboardController extends Controller
{
    /**
     * GET /api/member/dashboard
     * Live metrics for "Your Commerce This Month", 5 reward totals, and withdrawable balance.
     */
    public function summary(Request $request): JsonResponse
    {
        $member = $request->user();
        $startOfMonth = Carbon::now()->startOfMonth();

        // 1. Personal purchases & PV this month
        $personalOrders = Order::where('member_id', $member->id)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $startOfMonth)
            ->get();

        $personalPurchasesCents = (int) $personalOrders->sum('total_cents');
        $personalPv = (float) $personalOrders->sum('total_pv');

        // 2. Team GV (Downline volume this month)
        $downlineIds = $this->getDownlineMemberIds($member->id);
        $teamGv = (float) Order::whereIn('member_id', $downlineIds)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('total_pv');

        // 3. Five Reward Totals (fetched directly from member's ledger entries)
        $rewardTotals = [
            'member_purchase_reward_cents' => (int) round($this->getLedgerSumByBucket($member->id, 'member_reward', $startOfMonth) * 100),
            'distributor_performance_cents' => (int) round($this->getLedgerSumByBucket($member->id, 'distributor_bonus', $startOfMonth) * 100),
            'leadership_development_cents' => (int) round($this->getLedgerSumByBucket($member->id, 'leadership_bonus', $startOfMonth) * 100),
            'binary_team_bonus_cents'      => (int) round($this->getLedgerSumByBucket($member->id, 'binary_bonus', $startOfMonth) * 100),
            'matching_bonus_cents'         => (int) round($this->getLedgerSumByBucket($member->id, 'matching_bonus', $startOfMonth) * 100),
        ];

        $monthlyEarningsCents = array_sum($rewardTotals);

        // 4. Balances from Wallet Read-Model
        $wallets = Wallet::where('member_id', $member->id)->get();
        $withdrawableBalanceCents = (int) round($wallets->sum('available_balance') * 100);
        $pendingWithdrawalCents = (int) round($wallets->sum('pending_withdrawal') * 100);

        return response()->json([
            'personal_purchases_cents'   => $personalPurchasesCents,
            'personal_pv'                => $personalPv,
            'team_gv'                    => $teamGv,
            'monthly_earnings_cents'     => $monthlyEarningsCents,
            'withdrawable_balance_cents' => $withdrawableBalanceCents,
            'pending_withdrawal_cents'   => $pendingWithdrawalCents,
            'rewards'                    => $rewardTotals,
        ]);
    }

    /**
     * GET /api/member/rank-progress
     * Returns current rank, next rank target, progress bars, and one plain-language action sentence.
     */
    public function rankProgress(Request $request): JsonResponse
    {
        $member = $request->user();
        $member->load('currentRank');

        $currentRank = $member->currentRank ?: Rank::where('level', 1)->first();
        $nextRank = Rank::where('level', ($currentRank->level ?? 1) + 1)->first();

        if (!$nextRank) {
            // Already at maximum rank
            return response()->json([
                'current_rank'    => ['name' => $currentRank->name, 'level' => $currentRank->level],
                'next_rank'       => null,
                'action_sentence' => 'Congratulations! You have attained the highest platform rank.',
                'requirements'    => [],
            ]);
        }

        // Fetch qualification rule for next rank in member's country
        $rule = QualificationRule::where('rank_id', $nextRank->id)
            ->where(function ($q) use ($member) {
                $q->where('country', $member->country)->orWhereNull('country');
            })
            ->where('is_active', true)
            ->latest('effective_from')
            ->first();

        // Current progress metrics
        $startOfMonth = Carbon::now()->startOfMonth();
        $currentPv = (float) Order::where('member_id', $member->id)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('total_pv');

        $downlineIds = $this->getDownlineMemberIds($member->id);
        $currentGv = (float) Order::whereIn('member_id', $downlineIds)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('total_pv');

        $activeFrontline = $member->downline()->where('status', 'active')->count();

        $requirements = [
            [
                'key'     => 'personal_pv',
                'label'   => 'Personal PV',
                'current' => $currentPv,
                'target'  => (float) ($rule?->min_personal_pv ?? 300),
                'unit'    => 'PV',
            ],
            [
                'key'     => 'group_gv',
                'label'   => 'Group Volume (GV)',
                'current' => $currentGv,
                'target'  => (float) ($rule?->min_group_gv ?? 5000),
                'unit'    => 'GV',
            ],
            [
                'key'     => 'active_frontline',
                'label'   => 'Active Frontline',
                'current' => $activeFrontline,
                'target'  => (int) ($rule?->min_active_frontline ?? 5),
                'unit'    => 'members',
            ],
            [
                'key'     => 'qualified_legs',
                'label'   => 'Qualified Legs',
                'current' => 1,
                'target'  => (int) ($rule?->min_qualified_legs ?? 2),
                'unit'    => 'legs',
            ],
        ];

        // Construct one plain-language action sentence
        $pvGap = max(0, ($rule?->min_personal_pv ?? 300) - $currentPv);
        $frontlineGap = max(0, ($rule?->min_active_frontline ?? 5) - $activeFrontline);

        $actionSentence = "Sponsor {$frontlineGap} more active frontline members and reach {$pvGap} more personal PV to qualify for {$nextRank->name} rank this period.";

        return response()->json([
            'current_rank'    => ['name' => $currentRank->name, 'level' => $currentRank->level],
            'next_rank'       => ['name' => $nextRank->name, 'level' => $nextRank->level],
            'action_sentence' => $actionSentence,
            'requirements'    => $requirements,
        ]);
    }

    /**
     * GET /api/member/profile
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json(['member' => $request->user()]);
    }

    /**
     * PUT /api/member/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $member = $request->user();
        $validated = $request->validate([
            'phone'         => 'nullable|string|max:30',
            'city'          => 'nullable|string|max:100',
            'address'       => 'nullable|string|max:255',
            'national_id'   => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'bio'           => 'nullable|string|max:500',
        ]);

        $fields = ['phone', 'city', 'address', 'national_id', 'date_of_birth', 'bio'];
        $oldValues = $member->only($fields);
        $member->update($validated);

        AuditLog::record(
            event: 'member.profile_updated',
            actor: $member,
            subject: $member,
            oldValues: $oldValues,
            newValues: $member->only($fields),
            description: "Member {$member->email} updated profile & KYC details"
        );

        return response()->json(['message' => 'Profile and KYC details updated successfully.', 'member' => $member]);
    }

    protected function getLedgerSumByBucket(int $memberId, string $walletBucket, Carbon $since): float
    {
        return (float) LedgerEntry::where('member_id', $memberId)
            ->whereHas('allocationCategory', function ($q) use ($walletBucket) {
                $q->where('wallet_bucket', $walletBucket);
            })
            ->where('created_at', '>=', $since)
            ->sum('amount');
    }

    protected function getDownlineMemberIds(int $memberId, int $maxDepth = 10): array
    {
        $ids = [];
        $currentLevel = [$memberId];

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            if (empty($currentLevel)) break;
            $nextLevel = \App\Models\Member::whereIn('sponsor_id', $currentLevel)->pluck('id')->toArray();
            $ids = array_merge($ids, $nextLevel);
            $currentLevel = $nextLevel;
        }

        return array_unique($ids);
    }
}
