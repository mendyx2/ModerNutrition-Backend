<?php

namespace App\Commerce\Services;

use App\Exceptions\PlanVersionTransitionException;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\PlanVersion;
use Illuminate\Support\Facades\DB;

class PlanVersionActivationService
{
    /**
     * Transition a plan version from Draft to Approved.
     * Reconciles allocation percentages to required total before approving.
     */
    public function approve(PlanVersion $plan, Member $approver): PlanVersion
    {
        if ($plan->status !== 'draft') {
            throw new PlanVersionTransitionException(
                "Cannot approve PlanVersion #{$plan->id}: Current status is '{$plan->status}', must be 'draft'."
            );
        }

        // Validate percentage reconciliation (100%)
        $plan->validateAllocationTotal();

        $oldValues = $plan->only(['status', 'approved_by', 'approved_at']);

        $plan->update([
            'status'      => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        AuditLog::record(
            event: 'plan_version.approved',
            actor: $approver,
            subject: $plan,
            oldValues: $oldValues,
            newValues: $plan->only(['status', 'approved_by', 'approved_at']),
            metadata: ['country' => $plan->country],
            description: "Approved PlanVersion '{$plan->name}' for {$plan->country} by {$approver->email}"
        );

        return $plan;
    }

    /**
     * Transition a plan version from Approved to Active.
     * Supersedes previous active version for the same country without touching historical ledger entries.
     */
    public function activate(PlanVersion $plan, Member $activator): PlanVersion
    {
        if ($plan->status !== 'approved') {
            throw new PlanVersionTransitionException(
                "Cannot activate PlanVersion #{$plan->id}: Current status is '{$plan->status}', must be 'approved' first."
            );
        }

        // Validate percentage reconciliation again before go-live
        $plan->validateAllocationTotal();

        return DB::transaction(function () use ($plan, $activator) {
            // Find existing active plan for this country to supersede
            $previousActive = PlanVersion::where('country', $plan->country)
                ->where('status', 'active')
                ->where('id', '!=', $plan->id)
                ->first();

            if ($previousActive) {
                $prevOldValues = $previousActive->only(['status', 'superseded_by', 'effective_to']);
                $previousActive->update([
                    'status'        => 'superseded',
                    'superseded_by' => $plan->id,
                    'effective_to'  => now()->toDateString(),
                ]);

                AuditLog::record(
                    event: 'plan_version.superseded',
                    actor: $activator,
                    subject: $previousActive,
                    oldValues: $prevOldValues,
                    newValues: $previousActive->only(['status', 'superseded_by', 'effective_to']),
                    metadata: ['superseded_by_plan_id' => $plan->id],
                    description: "PlanVersion '{$previousActive->name}' superseded by new active plan '{$plan->name}'"
                );
            }

            // Activate the new plan version
            $oldValues = $plan->only(['status', 'activated_by', 'activated_at', 'effective_from']);
            $plan->update([
                'status'         => 'active',
                'activated_by'   => $activator->id,
                'activated_at'   => now(),
                'effective_from' => $plan->effective_from ?: now()->toDateString(),
            ]);

            AuditLog::record(
                event: 'plan_version.activated',
                actor: $activator,
                subject: $plan,
                oldValues: $oldValues,
                newValues: $plan->only(['status', 'activated_by', 'activated_at', 'effective_from']),
                metadata: [
                    'country' => $plan->country,
                    'previous_active_plan_id' => $previousActive?->id,
                ],
                description: "Activated PlanVersion '{$plan->name}' for {$plan->country}. Historical ledger entries remain permanently intact."
            );

            return $plan;
        });
    }
}
