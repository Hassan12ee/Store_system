<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\ApiResponseTrait;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderController extends Controller
{

    public function createOrderFromCart(Request $request)
    {
        // تحقق من صحة التوكن وجلب المستخدم
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // التحقق من البيانات المرسلة
        $request->validate([
            'address_id' => 'required|exists:addresses,id',
        ]);

        // الحصول على محتويات السلة
        $cartItems = Cart::where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // إنشاء الطلب
        $order = Order::create([
            'order_date' => now(),
            'customer_id' => $user->id,
            'address_id' => $request->address_id,
        ]);

        // إضافة تفاصيل الطلب من السلة
        foreach ($cartItems as $item) {
            OrderDetail::create([
                'order_id' => $order->order_id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->product->price, // التأكد إن العلاقة موجودة
            ]);
        }

        // حذف السلة بعد إنشاء الطلب
        Cart::where('user_id', $user->id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Order created from cart successfully',
            'data' => $order->load('details')
        ], 201);
    }


    public function getUserOrders(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $orders = Order::with(['details.product', 'address']) // load التفاصيل والعنوان
                    ->where('customer_id', $user->id)
                    ->orderBy('order_date', 'desc')
                    ->get();

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }

    
    public function show(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $order = Order::with(['details.product', 'address'])
                    ->where('customer_id', $user->id)
                    ->where('order_id', $id)
                    ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found or does not belong to the user'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $order
        ]);
    }
}

