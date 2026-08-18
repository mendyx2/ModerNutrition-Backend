<?php

namespace App\Commerce\Services;

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LedgerReversalService
{
    /**
     * Reverse a historical ledger entry by recording a new reversal row.
     * Original entry is NEVER updated or modified (append-only ledger).
     *
     * @param LedgerEntry $original
     * @param string $reason
     * @param Member $actor
     * @return LedgerEntry The new reversal row
     */
    public function reverse(LedgerEntry $original, string $reason, Member $actor): LedgerEntry
    {
        if ($original->is_reversal) {
            throw new InvalidArgumentException("Cannot reverse a reversal entry (#{$original->id}).");
        }

        // Check if already reversed
        $existingReversal = LedgerEntry::where('reversal_reference', $original->id)->first();
        if ($existingReversal) {
            throw new InvalidArgumentException("LedgerEntry #{$original->id} has already been reversed by Entry #{$existingReversal->id}.");
        }

        return DB::transaction(function () use ($original, $reason, $actor) {
            // 1. Write new append-only reversal row
            $reversalEntry = LedgerEntry::reverse($original, $reason, $actor->id);

            // 2. Adjust materialised Wallet read-model if applicable
            if ($original->member_id && $original->allocationCategory) {
                $wallet = Wallet::where('member_id', $original->member_id)
                    ->where('wallet_bucket', $original->allocationCategory->wallet_bucket)
                    ->where('currency', $original->currency)
                    ->first();

                if ($wallet) {
                    $originalAmount = (float) $original->amount;
                    $wallet->total_reversed = (float) $wallet->total_reversed + $originalAmount;
                    $wallet->available_balance = max(0, (float) $wallet->available_balance - $originalAmount);
                    $wallet->last_ledger_entry_at = now();
                    $wallet->save();
                }
            }

            // 3. Record audit log
            AuditLog::record(
                event: 'ledger.entry_reversed',
                actor: $actor,
                subject: $reversalEntry,
                oldValues: [
                    'original_entry_id' => $original->id,
                    'original_amount'   => (float) $original->amount,
                ],
                newValues: [
                    'reversal_entry_id' => $reversalEntry->id,
                    'reversal_amount'   => (float) $reversalEntry->amount,
                    'reason'            => $reason,
                ],
                metadata: [
                    'plan_version_id' => $original->plan_version_id,
                    'member_id'       => $original->member_id,
                    'currency'        => $original->currency,
                ],
                description: "Reversed LedgerEntry #{$original->id} with new Entry #{$reversalEntry->id}: {$reason}"
            );

            return $reversalEntry;
        });
    }
}
