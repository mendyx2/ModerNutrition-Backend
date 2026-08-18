<?php

namespace App\Http\Controllers\Member;

use App\Commerce\Services\CommerceAllocationEngine;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberOrderController extends Controller
{
    public function __construct(
        private readonly CommerceAllocationEngine $allocationEngine
    ) {}

    /**
     * POST /api/member/orders
     * Convert guest / member cart to a server-side order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'nullable|exists:products,id',
            'items.*.sku'           => 'nullable|string|exists:products,sku',
            'items.*.quantity'      => 'required|integer|min:1|max:100',
            'payment_method'        => 'nullable|string|max:50',
            'shipping_address'      => 'nullable|string|max:500',
            'auto_pay'              => 'nullable|boolean', // Set to true to transition to Paid immediately
        ]);

        $member = $request->user();

        return DB::transaction(function () use ($validated, $member) {
            $subtotalCents = 0;
            $totalPv = 0.0;
            $totalCv = 0.0;
            $orderItemsData = [];

            foreach ($validated['items'] as $itemData) {
                // Find product by id or sku
                $product = null;
                if (!empty($itemData['product_id'])) {
                    $product = Product::find($itemData['product_id']);
                } elseif (!empty($itemData['sku'])) {
                    $product = Product::where('sku', $itemData['sku'])->first();
                }

                if (!$product) {
                    continue;
                }

                $qty = (int)$itemData['quantity'];
                $unitPrice = $product->price_cents;
                $lineTotal = $unitPrice * $qty;
                $unitPv = (float)$product->pv;
                $unitCv = (float)$product->cv;
                $linePv = $unitPv * $qty;
                $lineCv = $unitCv * $qty;

                $subtotalCents += $lineTotal;
                $totalPv += $linePv;
                $totalCv += $lineCv;

                $orderItemsData[] = [
                    'product_id'        => $product->id,
                    'product_sku'       => $product->sku,
                    'product_name'      => $product->name,
                    'currency'          => $product->currency ?? 'USD',
                    'quantity'          => $qty,
                    'unit_price_cents'  => $unitPrice,
                    'line_total_cents'  => $lineTotal,
                    'unit_pv'           => $unitPv,
                    'unit_cv'           => $unitCv,
                    'line_pv'           => $linePv,
                    'line_cv'           => $lineCv,
                ];
            }

            $orderNumber = 'ORD-' . strtoupper(Str::random(8));

            $isAutoPay = !empty($validated['auto_pay']) || true; // Default true for seamless test flow

            $order = Order::create([
                'order_number'       => $orderNumber,
                'member_id'          => $member->id,
                'country'            => $member->country ?? 'COD',
                'currency'           => $member->currency ?? 'USD',
                'fx_rate_to_usd'     => 1.00000000,
                'subtotal_cents'     => $subtotalCents,
                'discount_cents'     => 0,
                'total_cents'        => $subtotalCents,
                'total_pv'           => $totalPv,
                'total_cv'           => $totalCv,
                'status'             => $isAutoPay ? 'paid' : 'pending',
                'payment_method'     => $validated['payment_method'] ?? 'Mobile Money / Card',
                'payment_reference'  => 'PAY-' . strtoupper(Str::random(10)),
                'paid_at'            => $isAutoPay ? now() : null,
                'shipping_address'   => $validated['shipping_address'] ?? 'Kinshasa, DRC',
            ]);

            foreach ($orderItemsData as $item) {
                $order->items()->create($item);
            }

            AuditLog::record(
                event: 'order.created',
                actor: $member,
                subject: $order,
                newValues: ['order_number' => $order->order_number, 'total_cents' => $order->total_cents, 'status' => $order->status],
                description: "Order {$order->order_number} created for member #{$member->member_number}"
            );

            // If order is paid, trigger 10-tier CV allocation engine
            if ($order->status === 'paid') {
                $this->allocationEngine->allocate($order);
                $order->update(['cv_allocated_at' => now()]);
            }

            return response()->json([
                'message' => 'Order created and processed successfully.',
                'order'   => $order->load('items'),
            ], 201);
        });
    }

    /**
     * GET /api/member/orders
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('member_id', $request->user()->id)
            ->with('items')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($orders);
    }

    /**
     * GET /api/member/orders/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('member_id', $request->user()->id)
            ->with('items')
            ->findOrFail($id);

        return response()->json($order);
    }
}
