<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Api\ApiResponseTrait;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use App\Models\Cart;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use App\Models\Favorite;

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

    // الحصول على المنتج من الكارت إن وجد
    $existingCart = Cart::where('user_id', $user->id)
                        ->where('product_id', $request->product_id)
                        ->first();

    $existingQuantity = $existingCart ? $existingCart->quantity : 0;

    // الكمية الجديدة بعد الإضافة
    $totalQuantity = $existingQuantity + 1;

    // التحقق من التوفر في المخزون
    if ($totalQuantity > $product->quantity) {
        return response()->json([
            'message' => 'Requested quantity exceeds available stock.',
            'available' => $product->quantity,
        ], 400);
    }

    // تحديث أو إنشاء الكارت
    $cart = Cart::updateOrCreate(
        ['user_id' => $user->id, 'product_id' => $request->product_id],
        ['quantity' => DB::raw("quantity + 1")]
    );

    return response()->json([
        'message' => 'Product added to cart successfully.',
        'cart' => $cart
    ]);
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
            'items' => $cartItems,
            'total' => round($total, 2)
        ]);
    }


    public function updateCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::guard('users')->user();

        // المنتج
        $product = Product::findOrFail($request->product_id);

        // تحقق من وجود الكارت
        $cart = Cart::where('user_id', $user->id)
                    ->where('product_id', $request->product_id)
                    ->first();

        if (!$cart) {
            return response()->json(['message' => 'Product not found in cart'], 404);
        }

        // تحقق من الكمية المتوفرة
        if ($request->quantity > $product->quantity) {
            return response()->json([
                'message' => 'Requested quantity exceeds available stock.',
                'available' => $product->quantity,
            ], 400);
        }

        // تحديث الكمية
        $cart->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Cart updated successfully',
            'cart' => $cart
        ]);
    }

    public function removeFromCart($id)
    {
        $user = Auth::guard('users')->user();

        $deleted = Cart::where('user_id', $user->id)->where('id', $id)->delete();

        return response()->json(['message' => $deleted ? 'Item removed' : 'Not found']);
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


    public function addToFavorites(Request $request)
    {
            $user = auth('api')->user();

            $request->validate([
                'product_id' => 'required|exists:products,id',
            ]);

            $already = Favorite::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->first();

            if ($already) {
                return response()->json(['message' => 'Product is already in favorites'], 409);
            }

            Favorite::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
            ]);

            return response()->json(['message' => 'Added to favorites successfully']);
    }


    public function getFavorites()
    {
        $Favorites = Favorite::with('product')->where('user_id', Auth::id())->get();

        return response()->json([

            'products' => collect($Favorites->items())->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->product->name,
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


    public function removeFromFavorites($productId)
    {
        $user = auth('api')->user();

        Favorite::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['message' => 'Removed from favorites']);
    }

}
