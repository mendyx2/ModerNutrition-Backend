<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    /**
     * GET /api/admin/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::latest('id');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, (int) $request->query('per_page', 20));
        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /api/admin/products
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'sku'                 => 'required|string|max:50|unique:products,sku',
            'name'                => 'required|string|max:150',
            'description'         => 'nullable|string',
            'category'            => 'required|string|max:50',
            'currency'            => 'required|string|size:3',
            'price_cents'         => 'required|integer|min:1',
            'pv'                  => 'required|numeric|min:0',
            'cv'                  => 'required|numeric|min:0',
            'available_countries' => 'nullable|array',
            'status'              => 'required|in:active,inactive,discontinued',
            'image_path'          => 'nullable|string|max:255',
        ]);

        $product = Product::create($validated);

        AuditLog::record(
            event: 'product.created',
            actor: $admin,
            subject: $product,
            oldValues: [],
            newValues: $validated,
            description: "Admin {$admin->email} created product '{$product->name}' ({$product->sku})"
        );

        return response()->json(['message' => 'Product created successfully.', 'product' => $product], 201);
    }

    /**
     * GET /api/admin/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(['product' => Product::findOrFail($id)]);
    }

    /**
     * PUT /api/admin/products/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'sku'                 => 'sometimes|string|max:50|unique:products,sku,' . $product->id,
            'name'                => 'sometimes|string|max:150',
            'description'         => 'nullable|string',
            'category'            => 'sometimes|string|max:50',
            'currency'            => 'sometimes|string|size:3',
            'price_cents'         => 'sometimes|integer|min:1',
            'pv'                  => 'sometimes|numeric|min:0',
            'cv'                  => 'sometimes|numeric|min:0',
            'available_countries' => 'nullable|array',
            'status'              => 'sometimes|in:active,inactive,discontinued',
            'image_path'          => 'nullable|string|max:255',
        ]);

        $oldValues = $product->only(array_keys($validated));
        $product->update($validated);

        AuditLog::record(
            event: 'product.updated',
            actor: $admin,
            subject: $product,
            oldValues: $oldValues,
            newValues: $validated,
            description: "Admin {$admin->email} updated product '{$product->name}' ({$product->sku})"
        );

        return response()->json(['message' => 'Product updated successfully.', 'product' => $product]);
    }

    /**
     * DELETE /api/admin/products/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $product = Product::findOrFail($id);

        $oldValues = $product->toArray();
        $product->delete();

        AuditLog::record(
            event: 'product.deleted',
            actor: $admin,
            subject: $product,
            oldValues: $oldValues,
            newValues: ['deleted_at' => now()],
            description: "Admin {$admin->email} soft-deleted product '{$product->name}' ({$product->sku})"
        );

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
