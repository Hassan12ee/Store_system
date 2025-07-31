<?php

namespace App\Http\Controllers\API\employees;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Models\DamagedProduct;
use App\Models\Product;
use App\Models\ReturnedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;



class ReturnedProductController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */

public function index(Request $request)
{
    $query = ReturnedProduct::with('product');

    // فلتر حسب المنتج
    if ($request->has('product_id')) {
        $query->where('product_id', $request->product_id);
    }

    // فلتر حسب التاريخ من
    if ($request->has('from_date')) {
        $query->whereDate('received_at', '>=', $request->from_date);
    }

    // فلتر حسب التاريخ إلى
    if ($request->has('to_date')) {
        $query->whereDate('received_at', '<=', $request->to_date);
    }

    // ترتيب من الأجدد للأقدم
    $query->orderBy('received_at', 'desc');

    // تقسيم صفحات
    $returnedProducts = $query->paginate(10);

    return response()->json([
        'status' => true,
        'current_page' => $returnedProducts->currentPage(),
        'per_page' => $returnedProducts->perPage(),
        'total' => $returnedProducts->total(),
        'last_page' => $returnedProducts->lastPage(),
        'next_page_url' => $returnedProducts->nextPageUrl(),
        'prev_page_url' => $returnedProducts->previousPageUrl(),
        'data' => collect($returnedProducts->items())->map(function ($item) {
            return [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'product_id' => $item->product_id,
                'product_name' => optional($item->product)->name,
                'quantity' => $item->quantity,
                'is_damaged' => $item->is_damaged,
                'return_reason' => $item->return_reason,
                'return_status' => $item->return_status,
                'received_at' => $item->received_at,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        }),
    ]);
}


    /**
     * Store a newly created resource in storage.
     */
public function store(Request $request)
{
    $request->validate([
        'order_id'        => 'required|exists:orders,id',
        'product_id'      => 'required|exists:products,id',
        'quantity_damaged'=> 'nullable|integer|min:0',
        'quantity_good'   => 'nullable|integer|min:0',
        'return_reason'   => 'nullable|string',
        'return_status'   => 'required|in:requested,processing,with_shipping_company,returned',
        'received_at'     => 'nullable|date',
    ]);

    $quantityDamaged = $request->quantity_damaged ?? 0;
    $quantityGood = $request->quantity_good ?? 0;

    if ($quantityDamaged + $quantityGood === 0) {
        return $this->apiResponse(null, 'At least one of quantity_damaged or quantity_good must be greater than 0.', 422);
    }

    DB::beginTransaction();

    try {
        // تسجيل الكمية الكلية المرتجعة
        $returned = ReturnedProduct::create([
            'order_id'      => $request->order_id,
            'product_id'    => $request->product_id,
            'quantity'      => $quantityDamaged + $quantityGood,
            'is_damaged'    => $quantityDamaged > 0,
            'return_reason' => $request->return_reason,
            'return_status' => $request->return_status,
            'received_at'   => $request->received_at ?? now(),
        ]);

        // إضافة الكمية التالفة
        if ($quantityDamaged > 0) {
            DamagedProduct::create([
                'product_id'  => $request->product_id,
                'quantity'    => $quantityDamaged,
                'reported_at' => now(),
                'notes'       => $request->return_reason,
            ]);
        }

        // إرجاع الكمية السليمة للمخزون
        if ($quantityGood > 0 && $request->return_status === 'returned') {
            Product::where('id', $request->product_id)
                ->increment('warehouse_quantity', $quantityGood);
        }

        DB::commit();

        return $this->apiResponse($returned, 'Returned product processed successfully.', 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return $this->apiResponse(null, 'Failed to process returned product. ' . $e->getMessage(), 500);
    }
}





}
