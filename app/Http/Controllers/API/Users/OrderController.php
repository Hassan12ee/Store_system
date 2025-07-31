<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\ApiResponseTrait;
use App\Models\Product;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\ReservedQuantity;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class OrderController extends Controller
{


    public function createOrderFromCart(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'address_id' => 'required|exists:addresses,id',
        ]);

        $cartItems = Cart::where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $totalPrice = 0;

            foreach ($cartItems as $item) {
                $product = Product::findOrFail($item->product_id);

                $reservation = ReservedQuantity::where('user_id', $user->id)
                    ->where('product_id', $item->product_id)
                    ->where('expires_at', '>', Carbon::now())
                    ->first();

                if ($reservation) {
                    if ($reservation->quantity < $item->quantity) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Reserved quantity is less than requested.',
                            'product_id' => $item->product_id,
                        ], 400);
                    }
                    // لا تخصم من المنتج هنا، لأنه اتخصم وقت الحجز
                } else {
                    // مفيش حجز، شوف المخزون
                    if ($product->quantity < $item->quantity) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Product stock is not enough and reservation expired.',
                            'product_id' => $item->product_id,
                        ], 400);
                    }

                    // خصم الكمية من المنتج مباشرة
                    $product->decrement('quantity', $item->quantity);
                }

                $totalPrice += $product->price * $item->quantity;
            }

            // إنشاء الطلب
            $order = Order::create([
                'order_date'  => now(),
                'customer_id' => $user->id,
                'address_id'  => $request->address_id,
                'total_price' => $totalPrice,
            ]);

            foreach ($cartItems as $item) {
                $product = Product::findOrFail($item->product_id);

                OrderDetail::create([
                    'order_id'   => $order->order_id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'price'      => $product->price,
                ]);

                // حذف الحجز لو موجود
                ReservedQuantity::where('user_id', $user->id)
                    ->where('product_id', $item->product_id)
                    ->delete();
            }

            // حذف الكارت
            Cart::where('user_id', $user->id)->delete();

            DB::commit();
            return response()->json([
                'status'  => true,
                'message' => 'Order created from cart successfully',
                'data'    => $order->load('details')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while creating the order.',
                'error'   => $e->getMessage()
            ], 500);
        }
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
        $formattedOrder = [
            'order_id' => $order->order_id,
            'order_date' => $order->order_date,
            'customer_id' => $order->customer_id,
            'address_id' => $order->address_id,
            'created_by_employee_id' => $order->created_by_employee_id,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'status' => $order->status,
            'total_price' => $order->total_price,
            'details' => $order->details->map(function ($detail) {
                $product = $detail->product;
                return [
                    'id' => $detail->id,
                    'order_id' => $detail->order_id,
                    'product_id' => $detail->product_id,
                    'quantity' => $detail->quantity,
                    'price' => $detail->price,
                    'created_at' => $detail->created_at,
                    'updated_at' => $detail->updated_at,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'barcode' => $product->barcode,
                        'Photos' => collect($product->Photos)->map(fn($photo) => asset($photo)), // ← تأكد إنها محفوظة JSON بالـ DB
                        'main_photo' =>  asset($product->main_photo),
                        'quantity' => $product->quantity,
                        'specifications' => $product->specifications,
                        'price' => $product->price,
                        'size' => $product->size,
                        'dimensions' => $product->dimensions,
                        'warehouse_id' => $product->warehouse_id,
                        'created_at' => $product->created_at,
                        'updated_at' => $product->updated_at,
                        'reserved_quantity' => $product->reserved_quantity ?? 0,
                    ]
                ];
            }),
            'address' => $order->address,
        ];
        return response()->json([
            'status' => true,
            'data' => $formattedOrder
        ]);
    }
}

// 🧾 حالات الطلب (Order Statuses):
// ordered → تم الطلب

// confirmed → تم التأكيد

// packing → يتم التغليف

// shipped_to_carrier → أُرسل إلى شركة الشحن

// out_for_delivery → يتم التوصيل

// delivered → تم التوصيل

