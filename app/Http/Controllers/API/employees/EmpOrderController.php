<?php

namespace App\Http\Controllers\API\employees;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class EmpOrderController extends Controller
{

    // عرض جميع الطلبات
    // يمكن استخدام هذا الجزء في عرض الطلبات للموظفين
    public function index(Request $request)
    {
        $query = Order::with(['details.product', 'address','address.governorate','address.city', 'customer']);

        if ($request->filled('governorate_id')) {
            $query->whereHas('address', function ($q) use ($request) {
                $q->where('governorate_id', $request->governorate);
            });
        }

        if ($request->filled('city_id')) {
            $query->whereHas('address', function ($q) use ($request) {
                $q->where('city_id', $request->city);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // فلتر التاريخ
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('order_date', [$request->from, $request->to]);
        } elseif ($request->filled('from')) {
            $query->whereDate('order_date', '>=', $request->from);
        } elseif ($request->filled('to')) {
            $query->whereDate('order_date', '<=', $request->to);
        }

        $orders = $query->latest('order_date')->paginate($request->get('per_page', 10));

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
                    'order_id' => $order->order_id,
                    'status' => $order->status,
                    'order_date' => $order->order_date,
                    'created_by_employee_id' => $order->created_by_employee_id,
                    'customer_id' => $order->customer_id,
                    'customer_name' => optional($order->customer)->FristName . ' ' . optional($order->customer)->LastName,
                    'Phone' => optional($order->customer)->Phone,
                    'address' => $order->address,
                    'total_items' => $order->details->sum('quantity'),
                    'created_at' => $order->created_at,
                ];
            }),
        ]);
    }

    // عرض تفاصيل طلب معين
    public function show($id)
    {
        $order = Order::with(['details.product', 'address','address.governorate','address.city', 'customer'])->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'order_id' => $order->order_id,
                 'status' => $order->status,
                'order_date' => $order->order_date,
                'created_by_employee_id'  => $order->created_by_employee_id,
                'customer' => [
                    'id' => optional($order->customer)->id,
                    'FristName' => optional($order->customer)->FristName,
                    'LastName' => optional($order->customer)->LastName,
                    'email' => optional($order->customer)->email,
                    'Phone' => optional($order->customer)->Phone,
                    'Gender' => optional($order->customer)->Gender,
                ],
                'address' => $order->address,
                'items' => $order->details->map(function ($detail) {
                    return [
                        'id'=> $detail->id,
                        'product_variants_id' => $detail->product_id,
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
        $query = Order::with(['details.product', 'address','address.governorate','address.city', 'customer']);

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
        if (!auth()->user()->can('edit_orders')) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'status' => 'required|in:ordered,confirmed,packing,shipped_to_carrier,out_for_delivery,delivered,cancelled',
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

    // التحقق من وجود رقم الهاتف
    // هذا الجزء يستخدم في التحقق من وجود رقم الهاتف قبل إنشاء طلب جديد
    public function checkPhoneNumber(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $user = User::where('Phone', $request->phone)->first();

        return response()->json([
            'status' => true,
            'user_exists' => (bool) $user,
            'user_id' => $user ? $user->id : null,
        ]);
    }

    // إنشاء طلب جديد للزوار
    // هذا الجزء يستخدم في إنشاء طلب جديد للزوار الذين ليس لديهم حساب
    public function createOrderForGuest(Request $request)
    {
        // 1. تحقق ان الموظف مسجل دخول
        $employee = Auth::user();
        if (!$employee->can('add_orders')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Only employees can create orders.',
            ], 401);
        }


        // 2. تحقق ان الرقم ملوش حساب قبل كدا
        $userExists = User::where('Phone', $request->phone)->exists();
        if ($userExists) {
            return response()->json([
                'status' => false,
                'message' => 'This phone number is already associated with an account.',
            ], 400);
        }

        // 3. Validate الطلبية
        $request->validate([
            'first_name'    => 'required|string',
            'last_name'     => 'required|string',
            'phone'         => 'required|string',
            'governorate'   => 'required|string',
            'city'          => 'required|string',
            'street'        => 'required|string',
            'comments'      => 'nullable|string',
            'order_items'   => 'required|array|min:1',
            'order_items.*.product_id' => 'required|exists:product_variants,id',
            'order_items.*.quantity'   => 'required|integer|min:1',
            'order_items.*.price'      => 'required|numeric|min:0',
        ]);

        $randomPassword = str()->random(8);

        $user = User::create([
            'FristName' => $request->first_name,
            'LastName'  => $request->last_name,
            'Phone'     => $request->phone,
            'password'  => bcrypt($randomPassword),
        ]);

        $address = Address::create([
            'user_id'     => $user->id,
            'governorate' => $request->governorate,
            'city'        => $request->city,
            'street'      => $request->street,
            'comments'    => $request->comments,
        ]);

        return $this->createOrderDetails($request, $user, $address, $employee, $randomPassword);
    }

    // إنشاء طلب جديد لمستخدم مسجل
    // هذا الجزء يستخدم في إنشاء طلب جديد لمستخدم لديه حساب بالفعل
    public function createOrderForExistingUser(Request $request)
    {
        // 1. تحقق ان الموظف مسجل دخول
        $employee = Auth::user();
        if (!$employee->can('add_orders')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Only employees can create orders.',
            ], 401);
        }

        // 2. تحقق ان الرقم ليه حساب
        $user = User::where('Phone', $request->phone)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'This phone number does not have an associated account.',
            ], 404);
        }

        // 3. Validate الطلبية
            $request->validate([
                'address_id'    => 'nullable|exists:addresses,id', // اختيار عنوان قديم
                'order_items'   => 'required|array|min:1',
                'order_items.*.product_id' => 'required|exists:product_variants,id',
                'order_items.*.quantity'   => 'required|integer|min:1',
                'order_items.*.price'      => 'required|numeric|min:0',
            ]);

        // 4. تحديد العنوان
        if ($request->address_id) {
            // اختيار عنوان قديم
            $address = Address::where('id', $request->address_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$address) {
                return response()->json([
                    'status' => false,
                    'message' => 'Address not found for this user.',
                ], 404);
            }

        }

        // 5. إنشاء الطلب
        return $this->createOrderDetails($request, $user, $address, $employee);
    }

    // إنشاء تفاصيل الطلب
    // هذا الجزء مشترك بين الطلبات للزوار والمستخدمين المسجلين
    // يمكن استخدامه في createOrderForGuest و createOrderForExistingUser
    /**
     * @param Request $request
     * @param User $user
     * @param Address $address
     * @param $employee
     * @param string|null $randomPassword
     * @return \Illuminate\Http\JsonResponse
     */
    // $randomPassword is optional, used only for guest orders
    private function createOrderDetails(Request $request, $user, $address, $employee, $randomPassword = null)
    {

        $order = Order::create([
            'order_date'              => now(),
            'customer_id'             => $user->id,
            'created_by_employee_id'  => $employee->id,
            'address_id'              => $address->id,
            'total_price'             => collect($request->order_items)->sum(function ($item) {
                return $item['price'] * $item['quantity'];
            }),
        ]);

        foreach ($request->order_items as $item) {
            OrderDetail::create([
                'order_id'   => $order->order_id,
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
            ]);
        }

        $siteUrl = config('app.url');

        $response = [
            'status'  => true,
            'message' => 'Order created successfully.',
            'data'    => [
                'order_id'   => $order->order_id,
                'user_id'    => $user->id,
                'address_id' => $address->id,
            ]
        ];

        if ($randomPassword) {
            $response['data']['login_info'] = [
                'site_url'  => $siteUrl,
                'phone'     => $user->Phone,
                'password'  => $randomPassword,
            ];
        }

        return response()->json($response, 201);
    }



    // تحديث طلب
    // هذا الجزء يستخدم في تحديث الطلبات الموجودة
    // يمكن استخدامه في تعديل حالة الطلب أو تفاصيله
    // أو تفاصيل المنتجات داخل الطلب
    // أو تفاصيل العنوان أو المستخدم
    // أو حذف منتج من الطلب
    // أو إضافة منتج جديد للطلب
    // أو تعديل بيانات الطلب بشكل عام


public function updateOrder(Request $request, $id)
{
    $validated = $request->validate([
        'status' => 'nullable|string|in:ordered,confirmed,packing,shipped_to_carrier,out_for_delivery,delivered,cancelled',
        'address_id' => 'required_with:address|exists:addresses,id',
        'customer' => 'nullable|array',
        'customer.id' => 'required_with:customer|exists:Users,id',
        'customer.FristName' => 'nullable|string',
        'customer.LastName' => 'nullable|string',
        'customer.email' => 'nullable|email',
        'customer.Phone' => 'nullable|string',
        'items' => 'array',
        'items.*.id' => 'nullable|integer', // order_details.id
        'items.*.product_id' => 'nullable|integer|exists:product_variants,id',
        'items.*.quantity' => 'nullable|integer|min:1',
        'items.*.price' => 'nullable|numeric|min:0',
    ]);

    DB::beginTransaction();

    try {
        $order = Order::with(['details', 'customer', 'address'])->findOrFail($id);
         // تحديث حالة الطلب
    if (isset($validated['status'])) {
        $order->status = $validated['status'];
    }
    $order->save();

    // تحديث بيانات العنوان
    if (!empty($validated['address_id'])) {
        $address = $order->address_id;
        if ($address != $validated['address_id']) {
            $order->address_id = $validated['address_id'];
            $order->save();
        }
    }

    // تحديث بيانات العميل
    if (!empty($validated['customer'])) {
        $customer = $order->customer;
        if ($customer) {
            $customer->update(array_filter([
                'FristName' => $validated['customer']['FristName'] ?? null,
                'LastName' => $validated['customer']['LastName'] ?? null,
                'email' => $validated['customer']['email'] ?? null,
                'Phone' => $validated['customer']['Phone'] ?? null,
            ]));
        }
    }


        foreach ($validated['items'] as $item) {

            // ✏ تعديل منتج موجود
            if (!empty($item['id'])) {
                $detail = $order->details()->where('id', $item['id'])->first();
                if ($detail) {
                    $product = ProductVariant::findOrFail($item['product_id']);
                    $oldQty = $detail->quantity;
                    $newQty = $item['quantity'];
                    $diff = $newQty - $oldQty;


                 if ($diff > 0) {
                        if ($product->quantity < $diff) {
                            throw new \Exception("Not enough stock for {$product->name}");
                        }
                        $product->quantity -= $diff; // خصم الكمية الجديدة
                    } elseif ($diff < 0) {
                        $product->quantity += abs($diff); // رجع الكمية القديمة
                    }
                    $product->save();
                    $detail->update([
                        'product_id' => $item['product_id'],
                        'quantity' => $newQty,
                        'price' => $item['price'],
                    ]);
                }
            }

            // ➕ إضافة منتج جديد
            else {
                $product = ProductVariant::findOrFail($item['product_id']);
                if ($product->quantity < $item['quantity']) {
                    throw new \Exception("Not enough stock for {$product->name}");
                }
                $product->quantity -= $item['quantity'];
                $product->save();

                $order->details()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Order updated successfully'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 400);
    }
}
public function deleteProductFromOrder($detailId)
{
    DB::beginTransaction();

    try {
        $detail = OrderDetail::findOrFail($detailId);
        // استرجاع الكمية للمخزون
        $product = ProductVariant::findOrFail($detail->product_id);
        $product->quantity += $detail->quantity;
        $product->save();

        // حذف تفاصيل الطلب
        $detail->delete();

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Product removed from order successfully'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 400);
    }
}

}
