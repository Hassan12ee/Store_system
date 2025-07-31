<?php

namespace App\Http\Controllers\API\employees;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Contracts\Providers\Auth;

class EmpOrderController extends Controller
{
    //
    public function index(Request $request)
    {
        $query = Order::with(['address', 'details.product']);

        if ($request->has('governorate')) {
            $query->whereHas('address', function ($q) use ($request) {
                $q->where('governorate', $request->governorate);
            });
        }

        if ($request->has('city')) {
            $query->whereHas('address', function ($q) use ($request) {
                $q->where('city', $request->city);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('order_date', [$request->from, $request->to]);
        }

        $orders = $query->latest()->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => true,
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
            'next_page_url' => $orders->nextPageUrl(),
            'prev_page_url' => $orders->previousPageUrl(),
            'data' => collect($orders->items())->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'status' => $order->status,
                    'order_date' => $order->order_date,
                    'customer_id' => $order->customer_id,
                    'customer_name' => optional($order->customer)->name,
                    'address' => optional($order->address)->full_address ?? '-',
                    'total_items' => $order->details->sum('quantity'),
                    'created_at' => $order->created_at,
                ];
            }),
        ]);
    }


    // عرض تفاصيل طلب معين
    public function show($id)
    {
        $order = Order::with(['details.product', 'address', 'customer'])->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'status' => $order->status,
                'order_date' => $order->order_date,
                'customer' => [
                    'id' => optional($order->customer)->id,
                    'name' => optional($order->customer)->name,
                    'email' => optional($order->customer)->email,
                ],
                'address' => $order->address,
                'items' => $order->details->map(function ($detail) {
                    return [
                        'product_id' => $detail->product_id,
                        'product_name' => optional($detail->product)->name,
                        'quantity' => $detail->quantity,
                        'price' => $detail->price,
                    ];
                }),
                'created_at' => $order->created_at,
            ]
        ]);
    }


    // فلترة متقدمة: حسب التاريخ أو العميل
    public function filter(Request $request)
    {
        $query = Order::with(['details.product', 'address', 'customer']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('order_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('order_date', '<=', $request->to_date);
        }

        $orders = $query->orderBy('order_date', 'desc')
                        ->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => true,
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
            'next_page_url' => $orders->nextPageUrl(),
            'prev_page_url' => $orders->previousPageUrl(),
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'status' => $order->status,
                    'order_date' => $order->order_date,
                    'customer_name' => optional($order->customer)->name,
                    'total_items' => $order->details->sum('quantity'),
                    'created_at' => $order->created_at,
                ];
            }),
        ]);
    }


    // تحديث حالة الطلب
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:ordered,confirmed,packing,shipped_to_carrier,out_for_delivery,delivered',
        ]);

        $order = Order::findOrFail($id);
        $order->status = $request->status;
        $order->save();

        return response()->json([
            'status' => true,
            'message' => 'Order status updated.',
            'data' => [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'status' => $order->status,
                'updated_at' => $order->updated_at,
            ]
        ]);
    }


    public function createOrderForGuest(Request $request)
    {
        $request->validate([
            'first_name'    => 'required|string',
            'last_name'     => 'required|string',
            'phone'         => 'required|string',
            'governorate'   => 'required|string',
            'city'          => 'required|string',
            'street'        => 'required|string',
            'comments'      => 'nullable|string',
            'order_items'   => 'required|array|min:1',
            'order_items.*.product_id' => 'required|exists:products,id',
            'order_items.*.quantity'   => 'required|integer|min:1',
            'order_items.*.price'      => 'required|numeric|min:0',
        ]);

        // 1. إنشاء كلمة مرور عشوائية
        $randomPassword = str()->random(8);

        // 2. إنشاء المستخدم
        $user = User::create([
            'FristName' => $request->first_name,
            'LastName'  => $request->last_name,
            'Phone'     => $request->phone,
            'password'  => bcrypt($randomPassword),
        ]);

        // 3. إنشاء العنوان
        $address = Address::create([
            'user_id'     => $user->id,
            'governorate' => $request->governorate,
            'city'        => $request->city,
            'street'      => $request->street,
            'comments'    => $request->comments,
        ]);

        // 4. إنشاء الطلب
        $order = Order::create([
            'order_date'              => now(),
            'customer_id'             => $user->id,
            'created_by_employee_id'  => Auth::guard('employee')->id(),
            'address_id'              => $address->id,
            'total_price'             => collect($request->order_items)->sum(function ($item) {
                return $item['price'] * $item['quantity'];
            }),
        ]);

        // 5. إضافة تفاصيل الطلب
        foreach ($request->order_items as $item) {
            OrderDetail::create([
                'order_id'   => $order->order_id,
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
            ]);
        }

        // 6. إرسال بيانات الدخول (مثلاً في الرد أو SMS)
        $siteUrl = config('app.url'); // أو ضع رابط الموقع مباشرة

        return response()->json([
            'status'  => true,
            'message' => 'Order created for guest user.',
            'data'    => [
                'order_id'   => $order->order_id,
                'user_id'    => $user->id,
                'address_id' => $address->id,
                'login_info' => [
                    'site_url'  => $siteUrl,
                    'phone'     => $user->Phone,
                    'password'  => $randomPassword,
                ],
            ]
        ], 201);
    }
}
