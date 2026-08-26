<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrderCvAllocationJob;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    /**
     * GET /api/admin/orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['member:id,member_number,first_name,last_name,email'])
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
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('member', function ($mq) use ($search) {
                      $mq->where('email', 'like', "%{$search}%")
                         ->orWhere('member_number', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min(100, (int) $request->query('per_page', 20));
        return response()->json($query->paginate($perPage));
    }

    /**
     * GET /api/admin/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['member', 'items', 'processedBy'])->findOrFail($id);
        return response()->json(['order' => $order]);
    }

    /**
     * PUT /api/admin/orders/{id}/status
     * Changes order status. When transitioned to 'paid', triggers queued CV allocation engine job.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,paid,processing,shipped,delivered,cancelled,refunded',
            'notes'  => 'nullable|string|max:500',
        ]);

        $oldStatus = $order->status;
        $newStatus = $validated['status'];

        $updates = [
            'status'       => $newStatus,
            'processed_by' => $admin->id,
            'notes'        => $validated['notes'] ?? $order->notes,
        ];

        if ($newStatus === 'paid' && !$order->paid_at) {
            $updates['paid_at'] = now();
        }

        $order->update($updates);

        AuditLog::record(
            event: 'order.status_changed',
            actor: $admin,
            subject: $order,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
            description: "Admin {$admin->email} updated Order #{$order->order_number} status from {$oldStatus} to {$newStatus}"
        );

        // TRIGGER CV ALLOCATION ENGINE ON PAID TRANSITION
        if ($newStatus === 'paid' && !$order->hasCvAllocated()) {
            try {
                app(\App\Commerce\Services\CommerceAllocationEngine::class)->allocate($order);
                $order->update(['cv_allocated_at' => now()]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Immediate CV allocation note for Order #{$order->order_number}: " . $e->getMessage());
                \App\Jobs\ProcessOrderCvAllocationJob::dispatch($order);
            }
        }

        return response()->json([
            'message' => "Order status updated to '{$newStatus}'.",
            'order'   => $order->fresh(['items', 'member']),
        ]);
    }
}
