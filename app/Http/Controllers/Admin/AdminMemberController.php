<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Rank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMemberController extends Controller
{
    /**
     * GET /api/admin/members
     * Paginated member list with status, country, and search filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Member::with(['currentRank:id,name,level', 'sponsor:id,member_number,first_name,last_name'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('country')) {
            $query->where('country', $request->query('country'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('member_number', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, (int) $request->query('per_page', 20));
        return response()->json($query->paginate($perPage));
    }

    /**
     * GET /api/admin/members/{id}
     */
    public function show(int $id): JsonResponse
    {
        $member = Member::with([
            'currentRank',
            'sponsor',
            'parent',
            'wallets',
            'roles',
        ])->findOrFail($id);

        return response()->json(['member' => $member]);
    }

    /**
     * POST /api/admin/members/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $member = Member::findOrFail($id);

        $oldValues = ['status' => $member->status];
        $member->update(['status' => 'active']);

        AuditLog::record(
            event: 'member.approved',
            actor: $admin,
            subject: $member,
            oldValues: $oldValues,
            newValues: ['status' => 'active'],
            description: "Admin {$admin->email} approved member account #{$member->member_number}"
        );

        return response()->json(['message' => 'Member account approved.', 'member' => $member]);
    }

    /**
     * POST /api/admin/members/{id}/suspend
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $member = Member::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $oldValues = ['status' => $member->status];
        $member->update(['status' => 'suspended']);

        // Revoke active API tokens
        $member->tokens()->delete();

        AuditLog::record(
            event: 'member.suspended',
            actor: $admin,
            subject: $member,
            oldValues: $oldValues,
            newValues: ['status' => 'suspended'],
            metadata: ['reason' => $validated['reason']],
            description: "Admin {$admin->email} suspended member #{$member->member_number}: {$validated['reason']}"
        );

        return response()->json(['message' => 'Member account suspended.', 'member' => $member]);
    }

    /**
     * POST /api/admin/members/{id}/rank-override
     */
    public function rankOverride(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $member = Member::findOrFail($id);

        $validated = $request->validate([
            'rank_id' => 'required|exists:ranks,id',
            'reason'  => 'required|string|max:255',
        ]);

        $newRank = Rank::findOrFail($validated['rank_id']);
        $oldRankId = $member->current_rank_id;

        $member->update(['current_rank_id' => $newRank->id]);

        // Sync Spatie additive roles
        $rolesToAssign = Rank::where('level', '<=', $newRank->level)->pluck('name')->toArray();
        $member->syncRoles($rolesToAssign);

        AuditLog::record(
            event: 'member.rank_overridden',
            actor: $admin,
            subject: $member,
            oldValues: ['current_rank_id' => $oldRankId],
            newValues: ['current_rank_id' => $newRank->id],
            metadata: ['new_rank_name' => $newRank->name, 'reason' => $validated['reason']],
            description: "Admin {$admin->email} overrode rank for member #{$member->member_number} to {$newRank->name}"
        );

        return response()->json(['message' => 'Rank override successful.', 'member' => $member->load('currentRank')]);
    }
}
