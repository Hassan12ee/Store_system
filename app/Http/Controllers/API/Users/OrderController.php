<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\ApiResponseTrait;


class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['details', 'address', 'customer'])->get();
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();

        $order = Order::create([
            'order_date' => now(),
            'customer_id' => $user->id,
            'address_id' => $request->address_id,
        ]);

        foreach ($request->items as $item) {
            OrderDetail::create([
                'order_id' => $order->order_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
        }

        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order->load('details')
        ], 201);
    }

    public function show($id)
    {
        $order = Order::with(['details', 'address'])->findOrFail($id);
        return response()->json($order);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->details()->delete();
        $order->delete();
        return response()->json(['message' => 'Order deleted successfully']);
    }
}
