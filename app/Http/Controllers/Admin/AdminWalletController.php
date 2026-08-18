<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    /**
     * GET /api/admin/wallets
     * Monitor all member wallets and categories across the platform.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Wallet::with(['member:id,member_number,first_name,last_name,email,country'])
            ->latest('updated_at');

        if ($request->filled('wallet_bucket')) {
            $query->where('wallet_bucket', $request->query('wallet_bucket'));
        }

        if ($request->filled('country')) {
            $query->where('country', $request->query('country'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('member_number', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, (int) $request->query('per_page', 25));
        return response()->json($query->paginate($perPage));
    }
}
