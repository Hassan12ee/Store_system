<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\ApiResponseTrait;


class OrderController extends Controller
{

public function createOrderFromCart(Request $request)
{
    $request->validate([
        'address_id' => 'required|exists:addresses,id',
    ]);

    $user = Auth::user();

    // Get user's cart items
    $cartItems = Cart::where('user_id', $user->id)->get();

    if ($cartItems->isEmpty()) {
        return response()->json([
            'status' => false,
            'message' => 'Cart is empty'
        ], 400);
    }

    // Create the order
    $order = Order::create([
        'order_date' => now(),
        'customer_id' => $user->id,
        'address_id' => $request->address_id,
    ]);

    // Create order details from cart
    foreach ($cartItems as $item) {
        OrderDetail::create([
            'order_id' => $order->order_id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'price' => $item->product->price, // assumes relationship exists
        ]);
    }

    // Clear user's cart
    Cart::where('user_id', $user->id)->delete();

    return response()->json([
        'status' => true,
        'message' => 'Order created from cart successfully',
        'data' => $order->load('details')
    ], 201);
}
}

