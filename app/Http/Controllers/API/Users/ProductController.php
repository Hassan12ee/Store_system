<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use App\Models\Cart;
use App\Models\ReservedQuantity;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductController extends Controller
{

    //
    use ApiResponseTrait;


    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
        'id' => $product->id,
        'name' => $product->name,
        'quantity' => $product->quantity,
        'specifications' => $product->specifications,
        'price' => $product->price,
        'size' => $product->size,
        'dimensions' => $product->dimensions,
        'warehouse_id' => $product->warehouse_id,
        'Photos' => collect($product->Photos)->map(fn($photo) => asset($photo)),
        'main_photo' => asset($product->main_photo),
        'created_at' => $product->created_at,
        'updated_at' => $product->updated_at,
        ]);

    }


    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $sortBy = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_direction', 'desc');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $minQuantity = $request->query('min_quantity');
        $maxQuantity = $request->query('max_quantity');

        $query = Product::query();

        // 🔍 بحث بالاسم
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // 💰 فلترة بالسعر
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        // 📦 فلترة بالكمية
        if ($minQuantity !== null) {
            $query->where('quantity', '>=', $minQuantity);
        }

        if ($maxQuantity !== null) {
            $query->where('quantity', '<=', $maxQuantity);
        }

        // ✅ الترتيب
        if (in_array($sortBy, ['name', 'price', 'quantity', 'created_at']) && in_array($sortDirection, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $products = $query->paginate($perPage);

        return response()->json([
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'last_page' => $products->lastPage(),
            'next_page_url' => $products->nextPageUrl(),
            'prev_page_url' => $products->previousPageUrl(),
            'products' => collect($products->items())->map(function ($product) {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'Photos' => collect($product->Photos)->map(fn($photo) => asset($photo)),
            'main_photo' => $product->main_photo ? asset($product->main_photo) : null,
            'quantity' => $product->quantity,
            'specifications' => $product->specifications,
            'price' => $product->price,
            'size' => $product->size,
            'dimensions' => $product->dimensions,
            'warehouse_id' => $product->warehouse_id,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
        }),

        ]);
    }


    public function addToCart(Request $request)
    {
        $request->validate([
                'product_id' => 'required|exists:products,id',
            ]);

            $user = Auth::guard('users')->user();

            $product = Product::findOrFail($request->product_id);

            // Check if there's already a reservation for this user & product
            $existingReservation = ReservedQuantity::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->where('expires_at', '>', Carbon::now())
                ->first();

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
                $reservation = ReservedQuantity::where('user_id', $user->id)
                    ->where('product_id', $product->id)
                    ->first();

                if ($reservation) {
                    $reservation->update([
                        'quantity'    => $reservation->quantity + 1,
                        'reserved_at' => now(),
                        'expires_at'  => now()->addMinutes(10),
                    ]);
                } else {
                    ReservedQuantity::create([
                        'user_id'     => $user->id,
                        'product_id'  => $product->id,
                        'quantity'    => 1,
                        'reserved_at' => now(),
                        'expires_at'  => now()->addMinutes(10),
                    ]);
                }

                // خصم الكمية من المنتج
                $product->decrement('quantity');

                // تحديث أو إنشاء الكارت
                $cart = Cart::where('user_id', $user->id)
                            ->where('product_id', $product->id)
                            ->first();

                if ($cart) {
                    $cart->update(['quantity' => $cart->quantity + 1]);
                } else {
                    Cart::create([
                        'user_id'    => $user->id,
                        'product_id' => $product->id,
                        'quantity'   => 1,
                    ]);
                }

                DB::commit();

                return response()->json([
                    'message' => 'Product added to cart and reserved.',
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['error' => 'Something went wrong.'], 500);
            }
    }



    public function getCart()
    {
        $user = Auth::guard('users')->user();

        $cartItems = Cart::with('product')
            ->where('user_id', $user->id)
            ->get();

        $total = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        return response()->json([
            'items' => collect($cartItems)->map(function ($product) {
            return [
                "id"=> $product->id,
                "user_id"=>$product->user_id,
                "product_id"=>$product->product_id,
                "quantity"=>$product->quantity,
                "created_at"=> $product->created_at,
                "updated_at"=> $product->updated_at,
                'name' => $product->product->name,
                'main_photo' => $product->product->main_photo ? asset($product->product->main_photo) : null,
                'specifications' => $product->product->specifications,
                'price' => $product->product->price,



            ];
            }),
            'total' => round($total, 2)
        ]);
    }


    public function updateCart(Request $request)
    {
         $request->validate([
        'product_id' => 'required|exists:products,id',
        'quantity'   => 'required|integer|min:1',
        ]);

        $user = Auth::guard('users')->user();

        $product = Product::findOrFail($request->product_id);
        $cart = Cart::where('user_id', $user->id)
                    ->where('product_id', $request->product_id)
                    ->first();

        $reservation = ReservedQuantity::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if (!$cart || !$reservation) {
            return response()->json(['message' => 'Product not found in cart or not reserved.'], 404);
        }

        $oldQty = $cart->quantity;
        $newQty = $request->quantity;
        $diff = $newQty - $oldQty;

        if ($diff > 0) {
            if ($diff > $product->quantity) {
                return response()->json([
                    'message'  => 'Requested quantity exceeds available stock.',
                    'available' => $product->quantity,
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Update cart quantity
            $cart->update(['quantity' => $newQty]);

            // Update reservation
            $reservation->update([
                'quantity'    => $newQty,
                'expires_at'  => now()->addMinutes(10),
            ]);

            // Adjust product stock
            if ($diff > 0) {
                $product->decrement('quantity', $diff);
            } elseif ($diff < 0) {
                $product->increment('quantity', abs($diff));
            }

            DB::commit();
            return response()->json(['message' => 'Cart updated successfully.', 'cart' => $cart]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }

    public function removeFromCart($id)
    {


        $user = Auth::guard('users')->user();

        $cart = Cart::where('user_id', $user->id)
                    ->where('product_id', $id)
                    ->first();

        $reservation = ReservedQuantity::where('user_id', $user->id)
            ->where('product_id', $id)
            ->first();

        if (!$cart || !$reservation) {
            return response()->json(['message' => 'Product not found in cart or reservation.'], 404);
        }

        DB::beginTransaction();
        try {
            // رجع الكمية للمخزون
            $product = Product::find($id);
            if ($product) {
                $product->increment('quantity', $reservation->quantity);
            }

            $cart->delete();
            $reservation->delete();

            DB::commit();
            return response()->json(['message' => 'Product removed from cart and reservation released.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }


    public function addToWishlist(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $userId = Auth::id();

        if (!$userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 🔍 تحقق إذا كان المنتج موجود بالفعل في الـ wishlist
        $existing = Wishlist::where('user_id', $userId)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Product is already in wishlist',
            ], 409); // 409 Conflict
        }

        // ✅ لو مش موجود، أضفه
        $wishlist = Wishlist::create([
            'user_id' => $userId,
            'product_id' => $request->product_id,
        ]);

        return response()->json([
            'message' => 'Product added to wishlist successfully',
            'wishlist' => $wishlist,
        ]);
    }


    public function getWishlist()
    {
        $wishlist = Wishlist::with('product')->where('user_id', Auth::id())->get();

        return response()->json([

            'products' => collect($wishlist)->map(function ($product) {
            return [
                "id"=> $product->id,
                "user_id"=>$product->user_id,
                "product_id"=>$product->product_id,
                "created_at"=> $product->created_at,
                "updated_at"=> $product->updated_at,
                'products' =>[
                'id' => $product->product->id,
                'name' => $product->product->name,
                'Photos' => collect($product->product->Photos)->map(fn($photo) => asset($photo)),
                'main_photo' => $product->product->main_photo ? asset($product->main_photo) : null,
                'quantity' => $product->product->quantity,
                'specifications' => $product->product->specifications,
                'price' => $product->product->price,
                'size' => $product->product->size,
                'dimensions' => $product->product->dimensions,
                'warehouse_id' => $product->product->warehouse_id,
                'created_at' => $product->product->created_at,
                'updated_at' => $product->product->updated_at,],

            ];
            }),
        ]);
    }


    public function removeFromWishlist($id)
    {


        $user = Auth::guard('users')->user();

        $wishlistItem = Wishlist::where('user_id', $user->id)
                                ->where('product_id', $id)
                                ->first();

        if (!$wishlistItem) {
            return response()->json([
                'message' => 'Product not found in wishlist'
            ], 404);
        }

        $wishlistItem->delete();

        return response()->json([
            'message' => 'Product removed from wishlist successfully'
        ]);
    }




}
