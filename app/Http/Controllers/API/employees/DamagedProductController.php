<?php

namespace App\Http\Controllers\API\employees;

use App\Models\DamagedProduct;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DamagedProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DamagedProduct::with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('reported_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('reported_at', '<=', $request->to_date);
        }

        $query->orderBy('reported_at', 'desc');

        $damagedProducts = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => true,
            'current_page' => $damagedProducts->currentPage(),
            'per_page' => $damagedProducts->perPage(),
            'total' => $damagedProducts->total(),
            'last_page' => $damagedProducts->lastPage(),
            'next_page_url' => $damagedProducts->nextPageUrl(),
            'prev_page_url' => $damagedProducts->previousPageUrl(),
            'data' => $damagedProducts->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => optional($item->product)->name,
                    'quantity' => $item->quantity,
                    'reported_at' => $item->reported_at,
                    'notes' => $item->notes,
                    'created_at' => $item->created_at,
                ];
            }),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'quantity'    => 'required|integer|min:1',
            'reported_at' => 'required|date',
            'notes'       => 'nullable|string',
        ]);

        $product = Product::find($validated['product_id']);

        if ($product->quantity < $validated['quantity']) {
            return response()->json([
                'status' => false,
                'message' => 'Not enough quantity in stock to mark as damaged.',
            ], 400);
        }

        // خصم الكمية من المخزون
        $product->decrement('quantity', $validated['quantity']);

        // حفظ سجل التالف
        $damaged = DamagedProduct::create([
            'product_id'  => $validated['product_id'],
            'quantity'    => $validated['quantity'],
            'reported_at' => $validated['reported_at'],
            'notes'       => $validated['notes'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Damaged product reported and quantity updated.',
            'data' => [
                'id' => $damaged->id,
                'product_id' => $damaged->product_id,
                'product_name' => optional($damaged->product)->name,
                'quantity' => $damaged->quantity,
                'reported_at' => $damaged->reported_at,
                'notes' => $damaged->notes,
            ]
        ], 201);
    }



}
