<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberWalletController extends Controller
{
    /**
     * GET /api/member/wallets
     * Summary tiles & per-category wallet cards.
     */
    public function wallets(Request $request): JsonResponse
    {
        $member = $request->user();

        $wallets = Wallet::where('member_id', $member->id)
            ->with('allocationCategory:id,name,wallet_bucket')
            ->get();

        $totalEarnedCents = (int) round($wallets->sum('total_earned') * 100);
        $totalWithdrawnCents = (int) round($wallets->sum('total_withdrawn') * 100);
        $withdrawableCents = (int) round($wallets->sum('available_balance') * 100);
        $pendingCents = (int) round($wallets->sum('pending_withdrawal') * 100);

        $buckets = [
            'member_reward'      => 'Purchase Reward',
            'distributor_bonus'   => 'Distributor Bonus',
            'leadership_bonus'    => 'Leadership Bonus',
            'binary_bonus'        => 'Binary Bonus',
            'matching_bonus'      => 'Matching Bonus',
        ];

        $categoryWallets = [];
        foreach ($buckets as $bucket => $label) {
            $w = $wallets->firstWhere('wallet_bucket', $bucket);
            $categoryWallets[] = [
                'bucket'          => $bucket,
                'label'           => $label,
                'earned_cents'    => (int) round(($w?->total_earned ?? 0) * 100),
                'withdrawn_cents' => (int) round(($w?->total_withdrawn ?? 0) * 100),
                'available_cents' => (int) round(($w?->available_balance ?? 0) * 100),
            ];
        }

        return response()->json([
            'summary' => [
                'total_earned_cents' => $totalEarnedCents,
                'pending_cents'      => $pendingCents,
                'withdrawable_cents' => $withdrawableCents,
            ],
            'wallets' => $categoryWallets,
        ]);
    }

    /**
     * GET /api/member/transactions
     * Paginated ledger entries history from append-only ledger_entries table.
     */
    public function transactions(Request $request): JsonResponse
    {
        $member = $request->user();
        $category = $request->query('category', 'all');
        $perPage = min(50, (int) $request->query('per_page', 10));

        $query = LedgerEntry::where('member_id', $member->id)
            ->with(['allocationCategory:id,name,wallet_bucket', 'planVersion:id,name'])
            ->latest('id');

        if ($category !== 'all') {
            $query->whereHas('allocationCategory', function ($q) use ($category) {
                $q->where('wallet_bucket', $category);
            });
        }

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function (LedgerEntry $entry) {
            return [
                'id'                    => $entry->id,
                'date'                  => $entry->created_at?->format('Y-m-d H:i'),
                'type'                  => $entry->entry_type,
                'category'              => $entry->allocationCategory?->name ?? 'General',
                'description'           => $entry->description,
                'amount_cents'          => (int) round($entry->amount * 100),
                'currency'              => $entry->currency,
                'running_balance_cents' => (int) round(($entry->running_balance ?? 0) * 100),
                'is_reversal'           => (bool) $entry->is_reversal,
                'plan_version_name'     => $entry->planVersion?->name,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * POST /api/member/withdrawals
     * Request balance withdrawal.
     */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $member = $request->user();

        $validated = $request->validate([
            'amount'         => 'required|numeric|min:10',
            'wallet_bucket'  => 'required|string|in:member_reward,distributor_bonus,leadership_bonus,binary_bonus,matching_bonus',
            'payment_method' => 'required|string|max:50',
            'payment_details'=> 'required|array',
        ]);

        $wallet = Wallet::where('member_id', $member->id)
            ->where('wallet_bucket', $validated['wallet_bucket'])
            ->where('currency', $member->currency)
            ->first();

        if (!$wallet || (float) $wallet->available_balance < (float) $validated['amount']) {
            return response()->json([
                'message' => 'Insufficient withdrawable balance in selected category wallet.',
            ], 422);
        }

        $withdrawal = DB::transaction(function () use ($member, $wallet, $validated) {
            // Deduct available balance and hold in pending_withdrawal
            $amount = (float) $validated['amount'];
            $wallet->available_balance = (float) $wallet->available_balance - $amount;
            $wallet->pending_withdrawal = (float) $wallet->pending_withdrawal + $amount;
            $wallet->save();

            $wd = Withdrawal::create([
                'withdrawal_number' => Withdrawal::generateWithdrawalNumber(),
                'member_id'         => $member->id,
                'wallet_bucket'     => $validated['wallet_bucket'],
                'currency'          => $member->currency,
                'country'           => $member->country,
                'amount'            => $amount,
                'requester_id'      => $member->id, // Maker
                'status'            => 'pending',
                'payment_method'    => $validated['payment_method'],
                'payment_details'   => $validated['payment_details'],
                'requested_at'      => now(),
            ]);

            AuditLog::record(
                event: 'withdrawal.requested',
                actor: $member,
                subject: $wd,
                oldValues: [],
                newValues: ['amount' => $amount, 'status' => 'pending'],
                description: "Member {$member->email} requested withdrawal of {$amount} {$member->currency}"
            );

            return $wd;
        });

        return response()->json([
            'message'    => 'Withdrawal request submitted for review.',
            'withdrawal' => $withdrawal,
        ], 201);
    }
}
