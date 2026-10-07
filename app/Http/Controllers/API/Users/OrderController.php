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
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Wishlist;

class OrderController extends Controller
{

            // return response()->json([
            //     'status' => false,
            //     'code' => 'PENDING_ORDER',
            //     'message' => 'You already have an unshipped order in the last 7 days.',
            //     'order' => [
            //         'id' => $recentOrder->order_id,
            //         'status' => $recentOrder->status,
            //         'created_at' => $recentOrder->created_at,
            //     ]
            // ], 200);

    /**
     * 🛒 إنشاء طلب من الكارت (للمستخدمين والضيوف)
     */
    public function createOrderFromCart(Request $request)
    {
        $user = null;
        $isGuest = false;

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            // لو مفيش توكن → نعتبره ضيف
            $isGuest = true;
        }

        $request->validate([
            'address_id'   => 'required|exists:addresses,id',
            'guest_id'     => 'nullable|string',
            'guest_name'   => 'nullable|string',
            'guest_phone'  => 'nullable|string',
            'guest_email'  => 'nullable|email',
            'force_create' => 'nullable|boolean',
        ]);

        if ($isGuest && !$request->filled('guest_id')) {
            return response()->json(['error' => 'guest_id is required for guest checkout'], 400);
        }
        if ($isGuest && !$request->filled('guest_name')) {
            return response()->json(['error' => 'guest_name is required for guest checkout'], 400);
        }
        if ($isGuest && !$request->filled('guest_phone')) {
            return response()->json(['error' => 'guest_phone is required for guest checkout'], 400);
        }


        //  جلب الكارت
        $cartItems = $isGuest
            ? Cart::where('guest_id', $request->guest_id)->get()
            : Cart::where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // 📦 تحقق من وجود طلب سابق بنفس رقم الهاتف خلال آخر أسبوع
        $recentOrder = Order::whereHas('customer', function ($q) use ($user, $isGuest, $request) {
                if ($user) {
                    $q->where('id', $user->id);
                } elseif ($isGuest && $request->guest_phone) {
                    $q->where('Phone', $request->guest_phone);
                }
            })
            ->whereNotIn('status', ['Shipped', 'Delivered', 'Cancelled'])
            ->where('created_at', '>=', now()->subDays(7))
            ->latest()
            ->first();

        if (!$request->boolean('force_create') && $recentOrder) {
            return response()->json([
                'status' => false,
                'code' => 'PENDING_ORDER',
                'message' => 'You already have an active order within the last 7 days.',
                'actions' => [
                    'view_url' => route('orders.show', $recentOrder->order_id),
                    'edit_url' => route('orders.edit', $recentOrder->order_id),
                ],
                'order' => [
                    'id' => $recentOrder->order_id,
                    'status' => $recentOrder->status,
                    'created_at' => $recentOrder->created_at,
                ]
            ], 200);
        }

        DB::beginTransaction();
        try {
            $totalPrice = 0;

            foreach ($cartItems as $item) {
                $product = ProductVariant::findOrFail($item->product_id);

                $reservation = ReservedQuantity::when(!$isGuest, function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->when($isGuest, function ($query) use ($request) {
                        $query->where('guest_id', $request->guest_id);
                    })
                    ->where('product_id', $item->product_id)
                    ->where('expires_at', '>', now())
                    ->first();

                if ($reservation) {
                    if ($reservation->quantity < $item->quantity) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Reserved quantity is less than requested.',
                            'product_id' => $item->product_id,
                        ], 400);
                    }
                } else {
                    if ($product->quantity < $item->quantity) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Product stock is not enough or reservation expired.',
                            'product_id' => $item->product_id,
                        ], 400);
                    }
                    $product->decrement('quantity', $item->quantity);
                }

                $totalPrice += $product->price * $item->quantity;
            }

            // ✅ لو ضيف → إنشاء حساب أو استخدام الموجود بنفس رقم الهاتف
            if ($isGuest) {
                $password = Str::random(8);

                $user = User::firstOrCreate(
                    ['Phone' => $request->guest_phone],
                    [
                        'FristName' => $request->guest_name ?? 'Guest',
                        'LastName'  => '',
                        'email'     => $request->guest_email ?? 'guest_' . uniqid() . '@example.com',
                        'password'  => Hash::make($password),
                        'Gender'    => 'Male',
                        'Birthday'  => '2000-01-01',
                    ]
                );

                // ✉️ إرسال الباسورد
                try {
                    Mail::to($user->email)->send(new \App\Mail\GuestAccountMail($user, $password));
                } catch (\Throwable $th) {
                    // تجاهل الخطأ في حالة عدم وجود Mailer
                }


            }

            // 🧾 إنشاء الطلب
            $order = Order::create([
                'order_date'  => now(),
                'customer_id' => $user->id,
                'address_id'  => $request->address_id,
                'total_price' => $totalPrice,
            ]);

            foreach ($cartItems as $item) {
                $product = ProductVariant::findOrFail($item->product_id);

                OrderDetail::create([
                    'order_id'   => $order->order_id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'price'      => $product->price,
                ]);

                ReservedQuantity::when(!$isGuest, function ($query) use ($user, $item) {
                        $query->where('user_id', $user->id)
                            ->where('product_id', $item->product_id);
                    })
                    ->when($isGuest, function ($query) use ($request, $item) {
                        $query->where('guest_id', $request->guest_id)
                            ->where('product_id', $item->product_id);
                    })
                    ->delete();
            }

            // 🧹 حذف الكارت بعد الطلب
            $isGuest
                ? Cart::where('guest_id', $request->guest_id)->delete()
                : Cart::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Order created successfully',
                'order'   => $order->load('details'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Failed to create order',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }




    /**
     * 🛍️ جلب طلبات المستخدم مع تفاصيل المنتجات
     */
    public function getUserOrders(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // دمج طلبات الجيست السابقة مع حساب المستخدم بعد الدمج (اختياري)
        if ($request->has('guest_id')) {
            Order::where('guest_id', $request->guest_id)
                ->update(['customer_id' => $user->id, 'guest_id' => null]);
        }

        $orders = Order::with([
                        'details.product.product', // تحميل المنتج + تفاصيله الأصلية
                        'address'
                    ])
                    ->where('customer_id', $user->id)
                    ->orderBy('order_date', 'desc')
                    ->get();

        return response()->json([
            'status' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $orders->map(function ($order) {
                return [
                    'order_id'     => $order->order_id,
                    'order_date'   => $order->order_date,
                    'total_price'  => $order->total_price,
                    'status'       => $order->status ?? 'Pending',
                    'address'      => $order->address ? [
                        'id' => $order->address->id,
                        'address' => $order->address->address,
                        'city' => $order->address->city,
                        'governorate' => $order->address->governorate,
                    ] : null,
                    'products' => $order->details->map(function ($detail) {
                        return [
                            'product_id'   => $detail->product->id ?? null,
                            'name_En'      => $detail->product->product->name_En ?? null,
                            'price'        => $detail->price,
                            'quantity'     => $detail->quantity,
                            'photo'        => $detail->product->product->main_photo
                                                ? asset($detail->product->product->main_photo)
                                                : null,
                        ];
                    }),
                ];
            })
        ]);
    }

    /**
     * 📦 جلب تفاصيل طلب معين مع تفاصيل المنتجات
     */
    public function show(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ✅ تحميل العلاقات بشكل كامل (لو عندك product_variants → product)
        $order = Order::with(['details.product.product', 'address'])
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
            'status' => $order->status ?? 'Pending',
            'total_price' => $order->total_price,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'address' => $order->address ? [
                'id' => $order->address->id,
                'address' => $order->address->address,
                'city' => $order->address->city,
                'governorate' => $order->address->governorate,
            ] : null,

            'products' => $order->details->map(function ($detail) {
                $variant = $detail->product;
                $product = $variant->product ?? null;

                return [
                    'order_detail_id' => $detail->id,
                    'quantity' => $detail->quantity,
                    'price' => $detail->price,
                    'total' => $detail->quantity * $detail->price,
                    'sku_id' => $variant->id,
                    'product_id' => $product->id ?? null,
                    'name_Ar' => $product->name_Ar ?? null,
                    'name_En' => $product->name_En ?? null,
                    'sku_Ar' => $variant->sku_Ar ?? null,
                    'sku_En' => $variant->sku_En ?? null,
                    'photos' => collect($product->Photos ?? [])->map(fn($photo) => asset($photo)),
                    'main_photo' => $product->main_photo ? asset($product->main_photo) : null,
                    'barcode' => $variant->barcode ?? $product->barcode ?? null,
                    'quantity_available' => $variant->quantity ?? 0,
                    'warehouse_id' => $variant->warehouse_id ?? null,
                    'specifications' => $product->specifications ?? null,
                    'dimensions' => $variant->dimensions ?? null,
                    'brand' => $product->brand ? [
                        'id' => $product->brand->id,
                        'name' => $product->brand->name,
                        'logo' => $product->brand->logo ? asset($product->brand->logo) : null,
                    ] : null,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'image' => $product->category->image ? asset($product->category->image) : null,
                    ] : null,
                ];
            }),
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

