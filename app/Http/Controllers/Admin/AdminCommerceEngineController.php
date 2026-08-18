<?php

namespace App\Http\Controllers\Admin;

use App\Commerce\Services\PlanVersionActivationService;
use App\Http\Controllers\Controller;
use App\Models\AllocationCategory;
use App\Models\AuditLog;
use App\Models\PlanVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommerceEngineController extends Controller
{
    public function __construct(protected PlanVersionActivationService $activationService)
    {
    }

    /**
     * GET /api/admin/plan-versions
     */
    public function index(Request $request): JsonResponse
    {
        $query = PlanVersion::with(['allocationCategories', 'createdBy:id,email', 'approvedBy:id,email', 'activatedBy:id,email'])
            ->latest('id');

        if ($request->filled('country')) {
            $query->where('country', $request->query('country'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json(['plans' => $query->get()]);
    }

    /**
     * POST /api/admin/plan-versions
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'name'                      => 'required|string|max:150',
            'country'                   => 'required|string|size:3',
            'description'               => 'nullable|string',
            'effective_from'            => 'nullable|date',
            'required_allocation_total' => 'nullable|numeric',
        ]);

        $plan = PlanVersion::create([
            'name'                      => $validated['name'],
            'country'                   => strtoupper($validated['country']),
            'description'               => $validated['description'] ?? null,
            'effective_from'            => $validated['effective_from'] ?? null,
            'required_allocation_total' => $validated['required_allocation_total'] ?? 100.0000,
            'status'                    => 'draft',
            'created_by'                => $admin->id,
        ]);

        AuditLog::record(
            event: 'plan_version.created',
            actor: $admin,
            subject: $plan,
            oldValues: [],
            newValues: $plan->toArray(),
            description: "Admin {$admin->email} created draft plan '{$plan->name}' ({$plan->country})"
        );

        return response()->json(['message' => 'Plan version created in draft status.', 'plan' => $plan], 201);
    }

    /**
     * POST /api/admin/plan-versions/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $plan = PlanVersion::findOrFail($id);

        $approvedPlan = $this->activationService->approve($plan, $admin);

        return response()->json([
            'message' => "Plan '{$approvedPlan->name}' approved. Allocation percentages successfully reconciled to 100%.",
            'plan'    => $approvedPlan->load('allocationCategories'),
        ]);
    }

    /**
     * POST /api/admin/plan-versions/{id}/activate
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $plan = PlanVersion::findOrFail($id);

        $activePlan = $this->activationService->activate($plan, $admin);

        return response()->json([
            'message' => "Plan '{$activePlan->name}' is now ACTIVE for {$activePlan->country}. Previous active plans superseded.",
            'plan'    => $activePlan->load('allocationCategories'),
        ]);
    }

    /**
     * GET /api/admin/allocation-categories
     */
    public function categories(Request $request): JsonResponse
    {
        $planVersionId = $request->query('plan_version_id');
        $query = AllocationCategory::query();

        if ($planVersionId) {
            $query->where('plan_version_id', $planVersionId);
        }

        return response()->json(['categories' => $query->orderBy('sort_order')->get()]);
    }

    /**
     * POST /api/admin/allocation-categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'plan_version_id'   => 'required|exists:plan_versions,id',
            'code'              => 'required|string|max:50',
            'name'              => 'required|string|max:100',
            'description'       => 'nullable|string',
            'percentage'        => 'required|numeric|min:0.0001|max:100',
            'wallet_bucket'     => 'required|string|max:50',
            'handler_class'     => 'nullable|string|max:255',
            'is_member_payable' => 'required|boolean',
            'is_pooled'         => 'required|boolean',
            'sort_order'        => 'nullable|integer',
        ]);

        $category = AllocationCategory::create($validated);

        AuditLog::record(
            event: 'allocation_category.created',
            actor: $admin,
            subject: $category,
            oldValues: [],
            newValues: $category->toArray(),
            description: "Admin {$admin->email} created allocation category '{$category->name}' ({$category->percentage}%)"
        );

        return response()->json(['message' => 'Allocation category created.', 'category' => $category], 201);
    }
}
