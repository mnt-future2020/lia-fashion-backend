<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = Order::with(['user', 'items.product.colors']);
            
            // Add date filtering if provided
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            // Add status filtering if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Add search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%");
                      });
                });
            }
            
            $query->orderBy('created_at', 'desc');
            
            // Use pagination parameter from request, default to 50 (much higher than 10)
            $perPage = $request->get('per_page', 50);
            $orders = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch orders: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch orders'
            ], 500);
        }
    }

    /**
     * Display the specified order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $order = Order::with(['user', 'items.product.colors'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch order: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch order'
            ], 500);
        }
    }

    /**
     * Update the order status.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */    public function updateStatus(Request $request, $id)
    {
        try {
            Log::info('Updating order status', [
                'order_id' => $id,
                'request_data' => $request->all()
            ]);

            $request->validate([
                'status' => 'required|string',
                'delivered_at' => 'nullable|date'
            ]);

            $order = Order::findOrFail($id);
            
            // Log initial order state
            Log::info('Current order state', [
                'order_id' => $order->id,
                'current_status' => $order->status,
                'current_delivered_at' => $order->delivered_at
            ]);            DB::beginTransaction();
            
            try {
                // Update basic status
                $order->status = $request->status;
                
                // Set delivered_at timestamp when order is marked as delivered
                if ($request->status === 'Delivered') {
                    // Convert ISO 8601 string to MySQL datetime format
                    $delivered_at = $request->delivered_at ? date('Y-m-d H:i:s', strtotime($request->delivered_at)) : now();
                    $order->delivered_at = $delivered_at;
                    Log::info('Setting delivered_at', [
                        'order_id' => $order->id,
                        'delivered_at' => $order->delivered_at
                    ]);
                }
                
                $order->save();
                
                DB::commit();

                // Log final order state
                Log::info('Order status updated successfully', [
                    'order_id' => $order->id,
                    'new_status' => $order->status,
                    'new_delivered_at' => $order->delivered_at
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Order status updated successfully'
                ]);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to update order status', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the order note.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateNote(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->notes = $request->note;
            $order->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Order note updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update order note: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order note'
            ], 500);
        }
    }
}