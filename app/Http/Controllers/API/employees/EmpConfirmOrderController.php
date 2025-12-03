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
use App\Models\OrderConfirmationSession;
use App\Models\OrderConfirmAttempt;


class EmpConfirmOrderController extends Controller
{

    public function workorganization()
    {
        $employee = auth()->user();

        if (!$employee->can('view_orders')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Only employees can view orders.',
            ], 401);
        }

        $employeeId = $employee->id;

        $session = OrderConfirmationSession::where('employee_id', $employeeId)
            ->where('status', 'in_progress')
            ->first();

        if ($session) {
            $order = Order::with(['details.product','details.product.product', 'address','address.governorate','address.city', 'customer'])->findOrFail($session->order_id);
             $secendTry=  OrderConfirmAttempt::where('order_id', $session->order_id)
            ->where('employee_id', $employeeId)
            ->where('method','call')
            ->orderBy('attempt_number', 'desc')
            ->first();
        if($secendTry){


            return response()->json([
                'session_id' => $session->id,
                'status' => true,
                    'data' => [
                        'order_id' => $order->order_id,
                        'status' => $order->status,
                        'order_date' => $order->order_date,
                        'total_price' => $order->total_price,
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
                                'product_name' => optional($detail->product)->product->name_Ar. ' ' . optional($detail->product)->sku_Ar,
                                'quantity' => $detail->quantity,
                                'price' => $detail->price,
                            ];
                        }),
                        'created_at' => $order->created_at,
                    ],
                'message' => 'Session started',
                'second_try' => true
            ]);
        }
        else{
            return response()->json([
            'session_id' => $session->id,
            'status' => true,
                'data' => [
                    'order_id' => $order->order_id,
                    'status' => $order->status,
                    'order_date' => $order->order_date,
                    'total_price' => $order->total_price,
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
                            'product_name' => optional($detail->product)->product->name_Ar. ' ' . optional($detail->product)->sku_Ar,
                            'quantity' => $detail->quantity,
                            'price' => $detail->price,
                        ];
                    }),
                    'created_at' => $order->created_at,
                ],
            'message' => 'Session started',
            'second_try' => false
            ]);

        }
        }

        return DB::transaction(function () use ($employeeId) {

            // 1️⃣ check old order 16 hours passed
            $oldOrder = Order::where('status', 'pending_confirmation')
                ->whereHas('attempts', function ($q) use ($employeeId)  {
                    $q->where('employee_id', $employeeId)
                    ->where('method','call')
                    ->where('created_at', '<=', now()->subHours(16));
                })
                ->orderBy('order_id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($oldOrder) {
                return $this->startConfirmation($oldOrder->order_id, $employeeId);
            }

            // 2️⃣ choose oldest untouched pending order
            $newOrder = Order::where('status', 'pending_confirmation')
                ->whereNull('employee_id')
                ->whereDoesntHave('attempts')
                ->orderBy('order_id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($newOrder) {
                $newOrder->employee_id = $employeeId;
                $newOrder->status = 'being_confirmed';

                $newOrder->save();



                return $this->startConfirmation($newOrder->order_id, $employeeId);
            }

            // 3️⃣ no orders available
            return response()->json([
                'order_id' => null,
                'type' => 'none'
            ]);
        });
    }


    private function startConfirmation($orderId, $employeeId)
    {

        $order = Order::with(['details.product','details.product.product', 'address','address.governorate','address.city', 'customer'])->findOrFail($orderId);
        $secendTry=  OrderConfirmAttempt::where('order_id', $orderId)
            ->where('employee_id', $employeeId)
            ->where('method','call')
            ->orderBy('attempt_number', 'desc')
            ->first();

        $session = OrderConfirmationSession::create([
            'order_id' => $orderId,
            'employee_id' => $employeeId,
            'started_at' => now(),
            'status' => 'in_progress'
        ]);
        if($secendTry){


            return response()->json([
                'session_id' => $session->id,
                'status' => true,
                    'data' => [
                        'order_id' => $order->order_id,
                        'status' => $order->status,
                        'order_date' => $order->order_date,
                        'total_price' => $order->total_price,
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
                            'product_name' => optional($detail->product)->product->name_Ar. ' ' . optional($detail->product)->sku_Ar,
                            'quantity' => $detail->quantity,
                            'price' => $detail->price,
                            ];
                        }),
                        'created_at' => $order->created_at,
                    ],
                'message' => 'Session started',
                'second_try' => true
            ]);
        }
        else{
            return response()->json([
            'session_id' => $session->id,
            'status' => true,
                'data' => [
                    'order_id' => $order->order_id,
                    'status' => $order->status,
                    'order_date' => $order->order_date,
                    'total_price' => $order->total_price,
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
                            'product_name' => optional($detail->product)->product->name_Ar. ' ' . optional($detail->product)->sku_Ar,
                            'quantity' => $detail->quantity,
                            'price' => $detail->price,
                        ];
                    }),
                    'created_at' => $order->created_at,
                ],
            'message' => 'Session started',
            'second_try' => false
            ]);

        }
    }

        public function logAttempt(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'method' => 'required|in:whatsapp,call',
            'attempt_number' => 'required|integer',
            'status' => 'required|in:sent_to_whatsapp,Closed,no_reply,Weak_connection,canceled,no_whatsapp,confirmed'
        ]);

        OrderConfirmAttempt::create([
            'order_id' => $request->order_id,
            'employee_id' => auth()->id(),
            'method' => $request->method,
            'attempt_number' => $request->attempt_number,
            'status' => $request->status
        ]);

        return response()->json(['message' => 'Attempt logged']);
    }

    public function endConfirmation(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:order_confirmation_sessions,id',
            'final_status' => 'required|in:confirmed,pending_confirmation,not_confirmed,canceled'
        ]);

        $session = OrderConfirmationSession::findOrFail($request->session_id);

        $session->ended_at = now();
        $session->status = 'completed';
        $session->save();

        $order = Order::find($session->order_id);
        $order->status = $request->final_status;
        $order->save();

        return response()->json(['message' => 'Session ended']);
    }

    // // فلترة متقدمة: حسب التاريخ أو العميل
    // public function filter(Request $request)
    // {
    //     $query = Order::with(['details.product', 'address','address.governorate','address.city', 'customer']);

    //     if ($request->filled('customer_id')) {
    //         $query->where('customer_id', $request->customer_id);
    //     }

    //     if ($request->filled('from_date')) {
    //         $query->whereDate('order_date', '>=', $request->from_date);
    //     }

    //     if ($request->filled('to_date')) {
    //         $query->whereDate('order_date', '<=', $request->to_date);
    //     }

    //     $orders = $query->orderBy('order_date', 'desc')
    //                     ->paginate($request->get('per_page', 10));

    //     return response()->json([
    //         'status' => true,
    //         'current_page' => $orders->currentPage(),
    //         'per_page' => $orders->perPage(),
    //         'total' => $orders->total(),
    //         'last_page' => $orders->lastPage(),
    //         'next_page_url' => $orders->nextPageUrl(),
    //         'prev_page_url' => $orders->previousPageUrl(),
    //         'data' => $orders->map(function ($order) {
    //             return [
    //                 'id' => $order->id,
    //                 'order_id' => $order->order_id,
    //                 'status' => $order->status,
    //                 'order_date' => $order->order_date,
    //                 'customer_name' => optional($order->customer)->name,
    //                 'total_items' => $order->details->sum('quantity'),
    //                 'created_at' => $order->created_at,
    //             ];
    //         }),
    //     ]);
    // }
}
