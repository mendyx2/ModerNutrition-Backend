<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicProductController extends Controller
{
    /**
     * GET /api/public/products
     * Public unauthenticated product catalogue consumed by ModerNutrition-Public.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('status', 'active')
            ->select([
                'id', 'sku', 'name', 'description', 'category',
                'currency', 'price_cents', 'pv', 'cv',
                'image_path', 'available_countries'
            ])
            ->latest('id');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('country')) {
            $country = strtoupper($request->query('country'));
            $query->where(function ($q) use ($country) {
                $q->whereNull('available_countries')
                  ->orWhereJsonContains('available_countries', $country);
            });
        }

        $perPage = min(50, (int) $request->query('per_page', 20));
        return response()->json($query->paginate($perPage));
    }

    /**
     * GET /api/public/products/{sku}
     * Public product detail.
     */
    public function show(string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)
            ->where('status', 'active')
            ->firstOrFail();

        return response()->json(['data' => $product]);
    }
}
