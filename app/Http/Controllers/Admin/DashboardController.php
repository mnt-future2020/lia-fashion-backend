<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Category;
use App\Models\User;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        try {
            $timeRange = $request->input('timeRange', 6);
            $startDate = Carbon::now()->subMonths($timeRange)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();

            // Current month data
            $currentMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            // Revenue stats
            $currentMonthRevenue = Order::where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$currentMonth, now()])
                ->sum('total_amount');

            $lastMonthRevenue = Order::where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
                ->sum('total_amount');

            $revenueGrowth = $lastMonthRevenue > 0 
                ? (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
                : 100;

            // Orders stats
            $currentMonthOrders = Order::whereBetween('created_at', [$currentMonth, now()])->count();
            $lastMonthOrders = Order::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
            $orderGrowth = $lastMonthOrders > 0 
                ? (($currentMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100 
                : 100;

            // New customers
            $currentMonthCustomers = User::whereBetween('created_at', [$currentMonth, now()])->count();
            $lastMonthCustomers = User::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
            $customerGrowth = $lastMonthCustomers > 0 
                ? (($currentMonthCustomers - $lastMonthCustomers) / $lastMonthCustomers) * 100 
                : 100;

            // Average order value
            $avgOrderValue = $currentMonthOrders > 0 ? $currentMonthRevenue / $currentMonthOrders : 0;
            $lastMonthAvgOrder = $lastMonthOrders > 0 ? $lastMonthRevenue / $lastMonthOrders : 0;
            $avgOrderGrowth = $lastMonthAvgOrder > 0 
                ? (($avgOrderValue - $lastMonthAvgOrder) / $lastMonthAvgOrder) * 100 
                : 100;

            // Revenue chart data
            $revenueData = Order::where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('COUNT(*) as orders')
                )
                ->orderBy('month')
                ->get()
                ->map(function($item) {
                    return [
                        'name' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                        'revenue' => round($item->revenue, 2),
                        'orders' => $item->orders
                    ];
                });

            // Top products with images
            $topProducts = Order::where('status', '!=', 'cancelled')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw('AVG(order_items.price) as avg_price'),
                    DB::raw('SUM(order_items.quantity) as total_quantity'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
                )
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get()
                ->map(function($product) {
                    // Get the full product with colors
                    $fullProduct = Product::with('colors')->find($product->id);
                    $color = $fullProduct->colors->first();
                    $mainImage = $color ? $color->cover_image : null;
                    
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'image' => $mainImage,
                        'price' => round($product->avg_price, 2),
                        'unitsSold' => $product->total_quantity,
                        'revenue' => round($product->total_revenue, 2)
                    ];
                });

            // Top categories
            $topCategories = Order::where('status', '!=', 'cancelled')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->select(
                    'categories.id',
                    'categories.name',
                    DB::raw('AVG(order_items.price) as avg_price'),
                    DB::raw('SUM(order_items.quantity) as total_quantity'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
                )
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get()
                ->map(function($category) {
                    // Get a representative product image for the category
                    $topProduct = Product::where('category_id', $category->id)
                        ->with('colors')
                        ->first();
                    $mainImage = null;
                    if ($topProduct && $topProduct->colors->first()) {
                        $mainImage = $topProduct->colors->first()->cover_image;
                    }
                    
                    return [
                        'name' => $category->name,
                        'image' => $mainImage,
                        'price' => round($category->avg_price, 2),
                        'unitsSold' => $category->total_quantity,
                        'revenue' => round($category->total_revenue, 2)
                    ];
                });

            // Recent transactions
            $recentTransactions = Order::with(['user'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function($order) {
                    return [
                        'id' => $order->order_number,
                        'customer' => $order->user ? $order->user->name : 'Guest',
                        'amount' => round($order->total_amount, 2),
                        'status' => $order->payment_status,
                        'date' => $order->created_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'kpiData' => [
                        'currentMonth' => [
                            'revenue' => round($currentMonthRevenue, 2),
                            'revenueGrowth' => round($revenueGrowth, 1),
                            'orders' => $currentMonthOrders,
                            'orderGrowth' => round($orderGrowth, 1),
                            'customers' => $currentMonthCustomers,
                            'customerGrowth' => round($customerGrowth, 1),
                            'avgOrderValue' => round($avgOrderValue, 2),
                            'avgOrderGrowth' => round($avgOrderGrowth, 1)
                        ]
                    ],
                    'revenueData' => $revenueData,
                    'topProducts' => $topProducts,
                    'topCategories' => $topCategories,
                    'recentTransactions' => $recentTransactions
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
