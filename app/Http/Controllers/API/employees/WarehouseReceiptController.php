<?php

namespace App\Http\Controllers\Api\employees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WarehouseReceipt;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseReceiptController extends Controller
{
    //

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity_received' => 'required|integer|min:1',
            'purchase_price' => 'nullable|numeric|min:0',
            'received_by_employee_id' => 'nullable|exists:employees,id',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $receipt = WarehouseReceipt::create([
                'product_id' => $request->product_id,
                'quantity_received' => $request->quantity_received,
                'purchase_price' => $request->purchase_price,
                'received_by_employee_id' => $request->received_by_employee_id,
                'notes' => $request->notes,
                'received_at' => now(),
            ]);

            Product::where('id', $request->product_id)
                ->increment('warehouse_quantity', $request->quantity_received);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Product quantity received and history recorded.',
                'data' => [
                    'id' => $receipt->id,
                    'product_id' => $receipt->product_id,
                    'quantity_received' => $receipt->quantity_received,
                    'purchase_price' => $receipt->purchase_price,
                    'received_by_employee_id' => $receipt->received_by_employee_id,
                    'notes' => $receipt->notes,
                    'received_at' => $receipt->received_at,
                    'created_at' => $receipt->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getProductHistory($productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.'
            ], 404);
        }

        $history = WarehouseReceipt::with('employee')
            ->where('product_id', $productId)
            ->orderBy('received_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'quantity_received' => $item->quantity_received,
                    'purchase_price' => $item->purchase_price,
                    'received_by_employee' => optional($item->employee)->name,
                    'received_at' => $item->received_at,
                    'notes' => $item->notes,
                    'created_at' => $item->created_at,
                ];
            });

        return response()->json([
            'status' => true,
            'product_id' => $productId,
            'product_name' => $product->name,
            'stock_quantity' => $product->warehouse_quantity,
            'history' => $history,
        ]);
    }


    public function filter(Request $request)
    {
        $query = WarehouseReceipt::with(['product', 'employee']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('employee_id')) {
            $query->where('received_by_employee_id', $request->employee_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('received_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('received_at', '<=', $request->to_date);
        }

        $query->orderBy('received_at', 'desc');

        $receipts = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => true,
            'current_page' => $receipts->currentPage(),
            'per_page' => $receipts->perPage(),
            'total' => $receipts->total(),
            'last_page' => $receipts->lastPage(),
            'next_page_url' => $receipts->nextPageUrl(),
            'prev_page_url' => $receipts->previousPageUrl(),
            'data' => collect($receipts->items())->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => optional($item->product)->name,
                    'quantity_received' => $item->quantity_received,
                    'purchase_price' => $item->purchase_price,
                    'received_by_employee' => optional($item->employee)->name,
                    'received_at' => $item->received_at,
                    'notes' => $item->notes,
                    'created_at' => $item->created_at,
                ];
            }),
        ]);
    }



}
