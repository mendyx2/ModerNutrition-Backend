<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-log
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('actor:id,email,member_number,first_name,last_name')
            ->latest('id');

        if ($request->filled('event')) {
            $query->where('event', 'like', "%{$request->query('event')}%");
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('actor_email', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, (int) $request->query('per_page', 25));
        return response()->json($query->paginate($perPage));
    }
}
