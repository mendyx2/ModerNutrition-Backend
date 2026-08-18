<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MarketingAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminMarketingAssetController extends Controller
{
    /**
     * GET /api/admin/marketing-assets
     */
    public function index(Request $request): JsonResponse
    {
        $query = MarketingAsset::with(['product:id,sku,name', 'createdBy:id,email'])
            ->latest('id');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = min(100, (int) $request->query('per_page', 20));
        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /api/admin/marketing-assets
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'title'            => 'required|string|max:150',
            'category'         => 'required|string|max:50',
            'asset_type'       => 'required|string|max:50',
            'product_id'       => 'nullable|exists:products,id',
            'dimensions'       => 'nullable|string|max:50',
            'format'           => 'nullable|string|max:20',
            'headline'         => 'nullable|string|max:255',
            'body_copy'        => 'nullable|string',
            'cta_text'         => 'nullable|string|max:100',
            'language'         => 'nullable|string|max:10',
            'target_countries' => 'nullable|array',
            'target_roles'     => 'nullable|array',
            'status'           => 'required|in:draft,active,archived',
            'file'             => 'nullable|file|max:51200', // 50MB max
            'file_path'        => 'nullable|string|max:255',
        ]);

        $filePath = $validated['file_path'] ?? 'assets/marketing/default.png';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('marketing_assets', 'public');
            $validated['format'] = $file->getClientOriginalExtension();
            $validated['file_size_bytes'] = $file->getSize();
        }

        $asset = MarketingAsset::create(array_merge($validated, [
            'file_path'  => $filePath,
            'created_by' => $admin->id,
        ]));

        AuditLog::record(
            event: 'marketing_asset.created',
            actor: $admin,
            subject: $asset,
            oldValues: [],
            newValues: $asset->toArray(),
            description: "Admin {$admin->email} created marketing asset '{$asset->title}'"
        );

        return response()->json(['message' => 'Marketing asset created.', 'asset' => $asset], 201);
    }

    /**
     * GET /api/admin/marketing-assets/{id}
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(['asset' => MarketingAsset::with(['product', 'createdBy'])->findOrFail($id)]);
    }

    /**
     * PUT /api/admin/marketing-assets/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $asset = MarketingAsset::findOrFail($id);

        $validated = $request->validate([
            'title'            => 'sometimes|string|max:150',
            'category'         => 'sometimes|string|max:50',
            'asset_type'       => 'sometimes|string|max:50',
            'product_id'       => 'nullable|exists:products,id',
            'dimensions'       => 'nullable|string|max:50',
            'format'           => 'nullable|string|max:20',
            'headline'         => 'nullable|string|max:255',
            'body_copy'        => 'nullable|string',
            'cta_text'         => 'nullable|string|max:100',
            'language'         => 'nullable|string|max:10',
            'target_countries' => 'nullable|array',
            'target_roles'     => 'nullable|array',
            'status'           => 'sometimes|in:draft,active,archived',
        ]);

        $oldValues = $asset->only(array_keys($validated));
        $asset->update(array_merge($validated, ['updated_by' => $admin->id]));

        AuditLog::record(
            event: 'marketing_asset.updated',
            actor: $admin,
            subject: $asset,
            oldValues: $oldValues,
            newValues: $validated,
            description: "Admin {$admin->email} updated marketing asset '{$asset->title}'"
        );

        return response()->json(['message' => 'Marketing asset updated.', 'asset' => $asset]);
    }

    /**
     * DELETE /api/admin/marketing-assets/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $asset = MarketingAsset::findOrFail($id);

        $oldValues = $asset->toArray();
        $asset->delete();

        AuditLog::record(
            event: 'marketing_asset.deleted',
            actor: $admin,
            subject: $asset,
            oldValues: $oldValues,
            newValues: ['deleted_at' => now()],
            description: "Admin {$admin->email} deleted marketing asset '{$asset->title}'"
        );

        return response()->json(['message' => 'Marketing asset deleted.']);
    }
}
