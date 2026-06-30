<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ShiprocketSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();
            
            $orders = Order::with(['items.product', 'user'])
                ->where('user_id', $user->id)  // Filter by authenticated user
                ->orderBy('created_at', 'desc')
                ->paginate(10);

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

    public function show($id)
    {
        try {
            $user = Auth::user();
            
            $order = Order::with(['items.product', 'user'])
                ->where('user_id', $user->id)  // Filter by authenticated user
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch order: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found or access denied'
            ], 404);
        }
    }

    public function updateStatus($id, Request $request)
    {
        try {
            $user = Auth::user();
            
            $request->validate([
                'status' => 'required|string'
            ]);

            $order = Order::where('user_id', $user->id)  // Filter by authenticated user
                ->findOrFail($id);
                
            $order->update([
                'status' => $request->status,
                'shipping_status' => $request->status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Order status updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update order status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order status'
            ], 500);
        }
    }

    public function updateNote($id, Request $request)
    {
        try {
            $user = Auth::user();
            
            $request->validate([
                'note' => 'required|string'
            ]);

            $order = Order::where('user_id', $user->id)  // Filter by authenticated user
                ->findOrFail($id);
                
            $order->update([
                'notes' => $request->note
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Note updated successfully'
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
