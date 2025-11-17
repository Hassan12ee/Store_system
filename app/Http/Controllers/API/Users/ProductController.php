<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\AttributeValue;
use App\Models\ProductVariantValue;
use App\Models\ReservedQuantity;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Milon\Barcode\DNS1D;

class ProductController extends Controller
{

    //
    use ApiResponseTrait;


    public function show($id)
    {
            $product = ProductVariant::with([
                'Product', // القيم والـ attributes المرتبطة
                'Product.brand',
                'Product.category'
            ])->find($id);

            if (!$product) {
                return $this->apiResponse(null, 'Product not found', 404);
            }

            return $this->apiResponse([
                'product'       => [
                    'sku_id'       => $product->id,
                    'product_id'             => $product->product->id,
                    'name_Ar'           => $product->product->name_Ar,
                    'name_En'           => $product->product->name_En,
                    'sku_Ar'      => $product->sku_Ar,
                    'sku_En'      => $product->sku_En,
                    'Photos' => collect($product->product->Photos)->map(fn($photo) => asset($photo)),
                    'main_photo'     => $product->product->main_photo ? asset($product->main_photo) : null,
                    'photo'    => $product->photo ? asset($variant->photo) : null,
                    'price'    => $product->price,
                    'quantity' => $product->quantity,
                    'warehouse_qty'  => $product->warehouse_quantity,
                    'specifications' => $product->product->specifications,
                    'dimensions'     => $product->dimensions,
                    'warehouse_id'   => $product->warehouse_id,
                    'barcode'        => $this->generateBarcodeBase64($product->barcode) ?? null,
                    'values_with_attributes' => $product->values->map(function ($value) {
                        return [
                            'value_id' => $value->id,
                            'attribute_id' => $value->attribute->id,
                            'attribute_name' => $value->attribute->name,
                            'value' => $value->value,
                            ];
                    }),
                    'brand' => $product->product->brand ? [
                        'id' => $product->product->brand->id,
                        'name' => $product->product->brand->name,
                        'logo' => $product->product->brand->logo ? asset($product->product->brand->logo) : null,
                    ] : null,
                    'category' => $product->category ? [
                        'id' => $product->product->category->id,
                        'name' => $product->product->category->name,
                        'image' => $product->product->category->image ? asset($product->product->category->image) : null,
                    ] : null,
                    'created_at'     => $product->product->created_at,
                    'updated_at'     => $product->product->updated_at,

                    ],



            ], 'Product details retrieved successfully', 200);
    }

    // توليد باركود بصيغة Base64
    private function generateBarcodeBase64($barcode)
    {
        if (empty($barcode)) {
            return null; // أو 'No barcode'
        }

        $generator = new DNS1D();
        $generator->setStorPath(storage_path('framework/barcodes'));
        $barcodeImage = $generator->getBarcodePNG($barcode, 'C128', 2, 60);
        return 'data:image/png;base64,' . $barcodeImage;
    }

    // عرض جميع المنتجات
    // هذا الدالة تعرض قائمة بجميع المنتجات مع إمكانية البحث والتصفية
    // يمكنك إضافة المزيد من المعايير حسب الحاجة
    // مثل: السعر، الكمية، التصنيف، إلخ.
    public function index(Request $request)
    {
        $perPage       = $request->query('per_page', 10);
        $search        = $request->query('search');
        $sortBy        = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_direction', 'desc');
        $minPrice      = $request->query('min_price');
        $maxPrice      = $request->query('max_price');
        $minQuantity   = $request->query('min_quantity');
        $maxQuantity   = $request->query('max_quantity');

        $query = ProductVariant::with([
            'Product', // القيم والـ attributes المرتبطة
            'Product.brand',
            'Product.category'
        ]);

        if ($search) {
             $query->whereHas('product', function ($q) use ($search) {
            $q->where('name_Ar', 'like', "%{$search}%");
            });
        }

        if ($minPrice !== null) {

                $query->where('price', '>=', $minPrice);

        }

        if ($maxPrice !== null) {

                $query->where('price', '<=', $maxPrice);

        }

        if ($minQuantity !== null) {

                $query->where('quantity', '>=', $minQuantity);

        }

        if ($maxQuantity !== null) {

                $query->where('quantity', '<=', $maxQuantity);

        }

        if (in_array($sortBy, ['name', 'created_at']) && in_array($sortDirection, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $products = $query->paginate($perPage);

        $data = [
            'current_page'   => $products->currentPage(),
            'per_page'       => $products->perPage(),
            'total'          => $products->total(),
            'last_page'      => $products->lastPage(),
            'next_page_url'  => $products->nextPageUrl(),
            'prev_page_url'  => $products->previousPageUrl(),
            'products'       => $products->map(function ($product) {
                return [
                    'sku_id'       => $product->id,
                    'product_id'             => $product->product->id,
                    'name_Ar'           => $product->product->name_Ar,
                    'name_En'           => $product->product->name_En,
                    'sku_Ar'      => $product->sku_Ar,
                    'sku_En'      => $product->sku_En,
                    'Photos' => collect($product->product->Photos)->map(fn($photo) => asset($photo)),
                    'main_photo'     => $product->product->main_photo ? asset($product->main_photo) : null,
                    'photo'    => $product->photo ? asset($variant->photo) : null,
                    'price'    => $product->price,
                    'quantity' => $product->quantity,
                    'warehouse_qty'  => $product->warehouse_quantity,
                    'specifications' => $product->product->specifications,
                    'dimensions'     => $product->dimensions,
                    'warehouse_id'   => $product->warehouse_id,
                    'barcode'        => $this->generateBarcodeBase64($product->barcode) ?? null,
                    'values_with_attributes' => $product->values->map(function ($value) {
                        return [
                            'value_id' => $value->id,
                            'attribute_id' => $value->attribute->id,
                            'attribute_name' => $value->attribute->name,
                            'value' => $value->value,
                            ];
                    }),
                    'brand' => $product->product->brand ? [
                        'id' => $product->product->brand->id,
                        'name' => $product->product->brand->name,
                        'logo' => $product->product->brand->logo ? asset($product->product->brand->logo) : null,
                    ] : null,
                    'category' => $product->category ? [
                        'id' => $product->product->category->id,
                        'name' => $product->product->category->name,
                        'image' => $product->product->category->image ? asset($product->product->category->image) : null,
                    ] : null,
                    'created_at'     => $product->product->created_at,
                    'updated_at'     => $product->product->updated_at,

                ];
            }),
        ];

        return $this->apiResponse($data, 'Product list retrieved successfully', 200);
    }



    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product_variants,id',
        ]);

        // تحقق إذا المستخدم مسجل أم ضيف
        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            // لو لا يوجد لا مستخدم ولا guest_id نولّد UUID جديد
            $guestId = (string) \Str::uuid();
        }

        $product = ProductVariant::findOrFail($request->product_id);

        // فلترة الحجوزات بناءً على user أو guest
        $reservationQuery = ReservedQuantity::query()
            ->where('product_id', $product->id)
            ->where('expires_at', '>', now());

        if ($user) {
            $reservationQuery->where('user_id', $user->id);
        } else {
            $reservationQuery->where('guest_id', $guestId);
        }

        $existingReservation = $reservationQuery->first();

        $existingQuantity = $existingReservation ? $existingReservation->quantity : 0;
        $totalRequested = $existingQuantity + 1;

        if ($totalRequested > $product->quantity) {
            return response()->json([
                'message' => 'Requested quantity exceeds available stock.',
                'available' => $product->quantity,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // تحديث أو إنشاء الحجز المؤقت
            $reservation = ReservedQuantity::query()
                ->where('product_id', $product->id);

            if ($user) {
                $reservation->where('user_id', $user->id);
            } else {
                $reservation->where('guest_id', $guestId);
            }

            $reservation = $reservation->first();

            if ($reservation) {
                $reservation->update([
                    'quantity'    => $reservation->quantity + 1,
                    'reserved_at' => now(),
                    'expires_at'  => now()->addMinutes(10),
                ]);
            } else {
                ReservedQuantity::create([
                    'user_id'     => $user?->id,
                    'guest_id'    => $guestId,
                    'product_id'  => $product->id,
                    'quantity'    => 1,
                    'reserved_at' => now(),
                    'expires_at'  => now()->addMinutes(10),
                ]);
            }

            // خصم الكمية من المنتج
            $product->decrement('quantity');

            // تحديث أو إنشاء الكارت
            $cartQuery = Cart::query()
                ->where('product_id', $product->id);

            if ($user) {
                $cartQuery->where('user_id', $user->id);
            } else {
                $cartQuery->where('guest_id', $guestId);
            }

            $cart = $cartQuery->first();

            if ($cart) {
                $cart->update(['quantity' => $cart->quantity + 1]);
            } else {
            $cart= Cart::create([
                    'user_id'    => $user?->id,
                    'guest_id'   => $guestId,
                    'product_id' => $product->id,
                    'quantity'   => 1,
                ]);
            }

            DB::commit();

            return response()->json([
                'message'  => 'Product added to cart and reserved.',
                'cart'     => $cart,
                'guest_id' => $guestId, // نرجعه للفرونت عشان يخزنه
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Something went wrong.', 'details' => $e->getMessage()], 500);
        }
    }



    public function getCart(Request $request)
    {
        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            return response()->json([
                'message' => 'No user or guest identifier provided.'
            ], 400);
        }

        $cartQuery = Cart::with('product');

        if ($user) {
            $cartQuery->where('user_id', $user->id);
        } else {
            $cartQuery->where('guest_id', $guestId);
        }

        $cartItems = $cartQuery->get();

        $total = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        return response()->json([
            'items' => collect($cartItems)->map(function ($product) {
                return [
                    "id"             => $product->id,
                    "user_id"        => $product->user_id,
                    "guest_id"       => $product->guest_id,
                    "product_id"     => $product->product->product_id,
                    "quantity"       => $product->quantity,
                    "created_at"     => $product->created_at,
                    "updated_at"     => $product->updated_at,
                    'sku_id'         => $product->product->id,
                    'name_Ar'        => $product->product->product->name_Ar,
                    'name_En'        => $product->product->product->name_En,
                    'sku_Ar'         => $product->product->sku_Ar,
                    'sku_En'         => $product->product->sku_En,
                    'main_photo'     => $product->product->product->main_photo
                        ? asset($product->product->product->main_photo)
                        : null,
                    'price'          => $product->product->price,
                    'specifications' => $product->product->product->specifications,
                    'dimensions'     => $product->product->dimensions,
                ];
            }),
            'total' => round($total, 2),
            'guest_id' => $guestId ?? null,
        ]);
    }

    public function updateCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product_variants,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            return response()->json(['message' => 'No user or guest identifier provided.'], 400);
        }

        $product = ProductVariant::findOrFail($request->product_id);

        // إعداد الاستعلامات حسب نوع المستخدم
        $cartQuery = Cart::where('product_id', $product->id);
        $reservationQuery = ReservedQuantity::where('product_id', $product->id);

        if ($user) {
            $cartQuery->where('user_id', $user->id);
            $reservationQuery->where('user_id', $user->id);
        } else {
            $cartQuery->where('guest_id', $guestId);
            $reservationQuery->where('guest_id', $guestId);
        }

        $cart = $cartQuery->first();
        $reservation = $reservationQuery->first();

        if (!$cart || !$reservation) {
            return response()->json(['message' => 'Product not found in cart or not reserved.'], 404);
        }

        $oldQty = $cart->quantity;
        $newQty = $request->quantity;
        $diff = $newQty - $oldQty;

        if ($diff > 0 && $diff > $product->quantity) {
            return response()->json([
                'message'  => 'Requested quantity exceeds available stock.',
                'available' => $product->quantity,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // تحديث الكمية في الكارت
            $cart->update(['quantity' => $newQty]);

            // تحديث الحجز
            $reservation->update([
                'quantity'   => $newQty,
                'expires_at' => now()->addMinutes(10),
            ]);

            // تعديل المخزون
            if ($diff > 0) {
                $product->decrement('quantity', $diff);
            } elseif ($diff < 0) {
                $product->increment('quantity', abs($diff));
            }

            DB::commit();

            return response()->json([
                'message'  => 'Cart updated successfully.',
                'cart'     => $cart,
                'guest_id' => $guestId ?? null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Something went wrong.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function removeFromCart(Request $request, $id)
    {
        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            return response()->json(['message' => 'No user or guest identifier provided.'], 400);
        }

        // البحث عن المنتج في الكارت بناءً على user أو guest
        $cartQuery = Cart::where('product_id', $id);
        $reservationQuery = ReservedQuantity::where('product_id', $id);

        if ($user) {
            $cartQuery->where('user_id', $user->id);
            $reservationQuery->where('user_id', $user->id);
        } else {
            $cartQuery->where('guest_id', $guestId);
            $reservationQuery->where('guest_id', $guestId);
        }

        $cart = $cartQuery->first();
        $reservation = $reservationQuery->first();

        if (!$cart || !$reservation) {
            return response()->json(['message' => 'Product not found in cart or reservation.'], 404);
        }

        DB::beginTransaction();
        try {
            // رجّع الكمية للمخزون
            $product = ProductVariant::find($id); // نفس النوع اللي استخدمته في addToCart
            if ($product) {
                $product->increment('quantity', $reservation->quantity);
            }

            // احذف الكارت والحجز
            $cart->delete();
            $reservation->delete();

            DB::commit();

            return response()->json([
                'message' => 'Product removed from cart and reservation released.',
                'guest_id' => $guestId ?? null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Something went wrong.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function addToWishlist(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product_variants,id',
        ]);

        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            return response()->json(['message' => 'No user or guest identifier provided.'], 400);
        }

        // 🔍 تحقق إذا كان المنتج موجود بالفعل في الـ wishlist
        $wishlistQuery = Wishlist::where('product_id', $request->product_id);

        if ($user) {
            $wishlistQuery->where('user_id', $user->id);
        } else {
            $wishlistQuery->where('guest_id', $guestId);
        }

        $existing = $wishlistQuery->first();

        if ($existing) {
            return response()->json([
                'message' => 'Product is already in wishlist',
            ], 409); // 409 Conflict
        }

        // ✅ لو مش موجود، أضفه
        $wishlistData = [
            'product_id' => $request->product_id,
        ];

        if ($user) {
            $wishlistData['user_id'] = $user->id;
        } else {
            $wishlistData['guest_id'] = $guestId;
        }

        $wishlist = Wishlist::create($wishlistData);

        return response()->json([
            'message' => 'Product added to wishlist successfully',
            'wishlist' => $wishlist,
            'guest_id' => $guestId ?? null,
        ]);
    }



    public function getWishlist(Request $request)
    {
        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            return response()->json(['message' => 'No user or guest identifier provided.'], 400);
        }

        // 🧩 جلب الـ wishlist بناءً على المستخدم أو الضيف
        $wishlistQuery = Wishlist::with('product');

        if ($user) {
            $wishlistQuery->where('user_id', $user->id);
        } else {
            $wishlistQuery->where('guest_id', $guestId);
        }

        $wishlist = $wishlistQuery->get();

        return response()->json([
            'products' => collect($wishlist)->map(function ($product) {
                return [
                    "id"         => $product->id,
                    "user_id"    => $product->user_id,
                    "guest_id"   => $product->guest_id ?? null,
                    "product_id" => $product->product_id,
                    "created_at" => $product->created_at,
                    "updated_at" => $product->updated_at,
                    'products' => [
                        'sku_id'        => $product->product->id,
                        'product_id'    => $product->product->product->id,
                        'name_Ar'       => $product->product->product->name_Ar,
                        'name_En'       => $product->product->product->name_En,
                        'sku_Ar'        => $product->product->sku_Ar,
                        'sku_En'        => $product->product->sku_En,
                        'Photos'        => collect($product->product->product->Photos)->map(fn($photo) => asset($photo)),
                        'main_photo'    => $product->product->product->main_photo ? asset($product->product->product->main_photo) : null,
                        'photo'         => $product->product->photo ? asset($product->product->photo) : null,
                        'price'         => $product->product->price,
                        'quantity'      => $product->product->quantity,
                        'warehouse_qty' => $product->product->warehouse_quantity,
                        'specifications'=> $product->product->product->specifications,
                        'dimensions'    => $product->product->dimensions,
                        'warehouse_id'  => $product->product->warehouse_id,
                        'barcode'       => $this->generateBarcodeBase64($product->product->barcode) ?? null,
                        'values_with_attributes' => $product->product->values->map(function ($value) {
                            return [
                                'value_id'       => $value->id,
                                'attribute_id'   => $value->attribute->id,
                                'attribute_name' => $value->attribute->name,
                                'value'          => $value->value,
                            ];
                        }),
                        'brand' => $product->product->product->brand ? [
                            'id'   => $product->product->product->brand->id,
                            'name' => $product->product->product->brand->name,
                            'logo' => $product->product->product->brand->logo ? asset($product->product->product->brand->logo) : null,
                        ] : null,
                        'category' => $product->product->product->category ? [
                            'id'    => $product->product->product->category->id,
                            'name'  => $product->product->product->category->name,
                            'image' => $product->product->product->category->image ? asset($product->product->product->category->image) : null,
                        ] : null,
                        'created_at' => $product->product->product->created_at,
                        'updated_at' => $product->product->product->updated_at,
                    ],
                ];
            }),
            'guest_id' => $guestId ?? null,
        ]);
    }


    public function removeFromWishlist(Request $request, $id)
    {
        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            return response()->json(['message' => 'No user or guest identifier provided.'], 400);
        }

        // 🔍 تجهيز الاستعلام بناءً على نوع المستخدم
        $wishlistQuery = Wishlist::where('product_id', $id);

        if ($user) {
            $wishlistQuery->where('user_id', $user->id);
        } else {
            $wishlistQuery->where('guest_id', $guestId);
        }

        $wishlistItem = $wishlistQuery->first();

        if (!$wishlistItem) {
            return response()->json([
                'message' => 'Product not found in wishlist'
            ], 404);
        }

        $wishlistItem->delete();

        return response()->json([
            'message' => 'Product removed from wishlist successfully',
            'guest_id' => $guestId ?? null,
        ]);
    }



    public function clearWishlist(Request $request)
    {
        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            return response()->json(['message' => 'No user or guest identifier provided.'], 400);
        }

        // 🔍 تجهيز الاستعلام
        $wishlistQuery = Wishlist::query();

        if ($user) {
            $wishlistQuery->where('user_id', $user->id);
        } else {
            $wishlistQuery->where('guest_id', $guestId);
        }

        $count = $wishlistQuery->count();

        if ($count === 0) {
            return response()->json([
                'message' => 'Wishlist already empty.',
                'guest_id' => $guestId ?? null,
            ]);
        }

        $wishlistQuery->delete();

        return response()->json([
            'message' => 'Wishlist cleared successfully.',
            'removed_items' => $count,
            'guest_id' => $guestId ?? null,
        ]);
    }

    public function clearCart(Request $request)
    {
        $user = Auth::user();
        $guestId = $request->header('X-Guest-Id') ?? $request->input('guest_id');

        if (!$user && !$guestId) {
            return response()->json(['message' => 'No user or guest identifier provided.'], 400);
        }

        DB::beginTransaction();
        try {
            // 🔍 تجهيز الاستعلامات
            $cartQuery = Cart::query();
            $reservationQuery = ReservedQuantity::query();

            if ($user) {
                $cartQuery->where('user_id', $user->id);
                $reservationQuery->where('user_id', $user->id);
            } else {
                $cartQuery->where('guest_id', $guestId);
                $reservationQuery->where('guest_id', $guestId);
            }

            $reservations = $reservationQuery->get();

            // ✅ رجّع الكميات إلى المخزون
            foreach ($reservations as $reserve) {
                $product = ProductVariant::find($reserve->product_id);
                if ($product) {
                    $product->increment('quantity', $reserve->quantity);
                }
            }

            // 🧹 حذف السجلات
            $cartQuery->delete();
            $reservationQuery->delete();

            DB::commit();

            return response()->json([
                'message' => 'Cart cleared successfully, all reservations released.',
                'guest_id' => $guestId ?? null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Something went wrong.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

}
