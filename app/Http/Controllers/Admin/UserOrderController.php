<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class UserOrderController extends Controller
{
    public function userOrders($id)
    {
        // Include order items and related product to allow frontend to display item names
        $orders = Order::with(['items.product'])
            ->where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['orders' => $orders]);
    }
    public function userOrderStats($id)
    {
        // Get total orders count
        $totalOrder = Order::where('user_id', $id)->count();
        
        // Get completed orders count (delivered)
        $completedOrder = Order::where('user_id', $id)
            ->where('status', 'delivered')
            ->count();
        
        // Get cancelled orders count
        $canceled = Order::where('user_id', $id)
            ->whereIn('status', ['cancelled', 'canceled'])
            ->count();
        
        // Get total spent (sum of paid orders)
        $totalSpent = Order::where('user_id', $id)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        return response()->json([
            'totalOrder' => $totalOrder,
            'completedOrder' => $completedOrder,
            'canceled' => $canceled,
            'totalSpent' => number_format($totalSpent, 2),
        ]);
    }
}
