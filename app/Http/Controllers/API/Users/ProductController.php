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
                'quantity' => 'required|integer|min:1'
            ]);

            $user = Auth::guard('users')->user();

            // تحديث لو المنتج موجود بالفعل
            $cart = Cart::updateOrCreate(
                ['user_id' => $user->id, 'product_id' => $request->product_id],
                ['quantity' => DB::raw("quantity + {$request->quantity}")]
            );

            return response()->json([
                'message' => 'Product added to cart',
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

    $userId =Auth::check() ? Auth::id() :null ;
    if (!$userId) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    $wishlist = Wishlist::firstOrCreate([
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
        'wishlist' => $wishlist
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


    public function removeFromFavorites($productId)
    {
        $user = auth('api')->user();

        Favorite::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['message' => 'Removed from favorites']);
    }

}
