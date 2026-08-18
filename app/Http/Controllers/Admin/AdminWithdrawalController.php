<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\PlanVersion;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWithdrawalController extends Controller
{
    /**
     * GET /api/admin/withdrawals
     */
    public function index(Request $request): JsonResponse
    {
        $query = Withdrawal::with(['member:id,member_number,first_name,last_name,email', 'requester:id,email', 'approver:id,email'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('country')) {
            $query->where('country', $request->query('country'));
        }

        $perPage = min(100, (int) $request->query('per_page', 20));
        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /api/admin/withdrawals/{id}/approve
     * MAKER-CHECKER ENFORCEMENT: Server-side reject if requester === approver.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $approver = $request->user();
        $withdrawal = Withdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['message' => "Withdrawal #{$withdrawal->withdrawal_number} is already {$withdrawal->status}."], 422);
        }

        // MAKER-CHECKER SECURITY CHECK: Reject same-user approval attempts
        if ($withdrawal->requester_id === $approver->id) {
            AuditLog::record(
                event: 'withdrawal.maker_checker_rejected',
                actor: $approver,
                subject: $withdrawal,
                description: "SECURITY VIOLATION: Admin {$approver->email} attempted to approve own withdrawal #{$withdrawal->withdrawal_number}"
            );

            return response()->json([
                'message' => 'Maker-Checker Security Policy: You cannot approve a withdrawal request that you initiated.',
                'error_code' => 'MAKER_CHECKER_VIOLATION',
            ], 403);
        }

        DB::transaction(function () use ($withdrawal, $approver) {
            $withdrawal->update([
                'status'       => 'approved',
                'approver_id'  => $approver->id,
                'reviewed_at'  => now(),
                'processed_at' => now(),
            ]);

            // Update Wallet read model: move from pending to total_withdrawn
            $wallet = Wallet::where('member_id', $withdrawal->member_id)
                ->where('wallet_bucket', $withdrawal->wallet_bucket)
                ->where('currency', $withdrawal->currency)
                ->first();

            if ($wallet) {
                $amount = (float) $withdrawal->amount;
                $wallet->pending_withdrawal = max(0, (float) $wallet->pending_withdrawal - $amount);
                $wallet->total_withdrawn = (float) $wallet->total_withdrawn + $amount;
                $wallet->last_ledger_entry_at = now();
                $wallet->save();
            }

            // Record append-only ledger entry for withdrawal debit
            $activePlan = PlanVersion::where('country', $withdrawal->country)->where('status', 'active')->first();

            LedgerEntry::record([
                'entry_type'      => 'withdrawal',
                'reference_type'  => Withdrawal::class,
                'reference_id'    => $withdrawal->id,
                'member_id'       => $withdrawal->member_id,
                'plan_version_id' => $activePlan?->id ?? 1,
                'currency'        => $withdrawal->currency,
                'country'         => $withdrawal->country,
                'amount'          => bcmul((string) $withdrawal->amount, '-1', 8),
                'running_balance' => $wallet ? (float) $wallet->available_balance : 0,
                'description'     => "Withdrawal payout WD-{$withdrawal->withdrawal_number} processed via {$withdrawal->payment_method}",
            ]);

            AuditLog::record(
                event: 'withdrawal.approved',
                actor: $approver,
                subject: $withdrawal,
                oldValues: ['status' => 'pending'],
                newValues: ['status' => 'approved', 'approver_id' => $approver->id],
                description: "Admin {$approver->email} approved withdrawal #{$withdrawal->withdrawal_number} (Maker: {$withdrawal->requester_id})"
            );
        });

        return response()->json([
            'message'    => 'Withdrawal approved and processed.',
            'withdrawal' => $withdrawal->fresh(['member', 'approver']),
        ]);
    }

    /**
     * POST /api/admin/withdrawals/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $approver = $request->user();
        $withdrawal = Withdrawal::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['message' => "Withdrawal #{$withdrawal->withdrawal_number} is already {$withdrawal->status}."], 422);
        }

        DB::transaction(function () use ($withdrawal, $approver, $validated) {
            $withdrawal->update([
                'status'           => 'rejected',
                'approver_id'      => $approver->id,
                'reviewed_at'      => now(),
                'rejection_reason' => $validated['reason'],
            ]);

            // Restore available balance in wallet
            $wallet = Wallet::where('member_id', $withdrawal->member_id)
                ->where('wallet_bucket', $withdrawal->wallet_bucket)
                ->where('currency', $withdrawal->currency)
                ->first();

            if ($wallet) {
                $amount = (float) $withdrawal->amount;
                $wallet->pending_withdrawal = max(0, (float) $wallet->pending_withdrawal - $amount);
                $wallet->available_balance = (float) $wallet->available_balance + $amount;
                $wallet->save();
            }

            AuditLog::record(
                event: 'withdrawal.rejected',
                actor: $approver,
                subject: $withdrawal,
                oldValues: ['status' => 'pending'],
                newValues: ['status' => 'rejected', 'rejection_reason' => $validated['reason']],
                description: "Admin {$approver->email} rejected withdrawal #{$withdrawal->withdrawal_number}: {$validated['reason']}"
            );
        });

        return response()->json([
            'message'    => 'Withdrawal request rejected. Funds returned to member wallet.',
            'withdrawal' => $withdrawal->fresh(['member']),
        ]);
    }
}
