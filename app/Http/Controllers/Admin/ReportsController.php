<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportsController extends Controller
{
    /**
     * Get dashboard overview statistics
     */
    public function getDashboardStats(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 30); // Default to 30 days
            $startDate = Carbon::now()->subDays($dateRange);
            $endDate = Carbon::now();

            // Total Orders
            $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
            
            // Previous period for comparison
            $prevStartDate = Carbon::now()->subDays($dateRange * 2);
            $prevEndDate = Carbon::now()->subDays($dateRange);
            $prevTotalOrders = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
            
            // Calculate percentage change
            $ordersChange = $prevTotalOrders > 0 ? (($totalOrders - $prevTotalOrders) / $prevTotalOrders) * 100 : ($totalOrders > 0 ? 100 : 0);

            // Total Revenue
            $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('payment_status', 'paid')
                ->sum('total_amount');
            
            $prevTotalRevenue = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])
                ->where('payment_status', 'paid')
                ->sum('total_amount');
            
            $revenueChange = $prevTotalRevenue > 0 ? (($totalRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100 : ($totalRevenue > 0 ? 100 : 0);

            // Average Order Value
            $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
            $prevAverageOrderValue = $prevTotalOrders > 0 ? $prevTotalRevenue / $prevTotalOrders : 0;
            $avgOrderValueChange = $prevAverageOrderValue > 0 ? (($averageOrderValue - $prevAverageOrderValue) / $prevAverageOrderValue) * 100 : ($averageOrderValue > 0 ? 100 : 0);

            // New Customers
            $newCustomers = User::whereBetween('created_at', [$startDate, $endDate])->count();
            $prevNewCustomers = User::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
            $newCustomersChange = $prevNewCustomers > 0 ? (($newCustomers - $prevNewCustomers) / $prevNewCustomers) * 100 : ($newCustomers > 0 ? 100 : 0);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_orders' => $totalOrders,
                    'orders_change' => round($ordersChange, 1),
                    'total_revenue' => $totalRevenue,
                    'revenue_change' => round($revenueChange, 1),
                    'average_order_value' => round($averageOrderValue, 2),
                    'avg_order_value_change' => round($avgOrderValueChange, 1),
                    'new_customers' => $newCustomers,
                    'new_customers_change' => round($newCustomersChange, 1),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch dashboard stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales trend data
     */
    public function getSalesTrend(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 30);
            $startDate = Carbon::now()->subDays($dateRange);
            $endDate = Carbon::now();

            // Determine grouping based on date range
            $groupBy = $dateRange <= 7 ? 'day' : ($dateRange <= 30 ? 'day' : 'month');
            
            $salesData = Order::select(
                    DB::raw("DATE_FORMAT(created_at, " . ($groupBy === 'day' ? "'%Y-%m-%d'" : "'%Y-%m'") . ") as period"),
                    DB::raw('SUM(total_amount) as sales'),
                    DB::raw('COUNT(*) as orders')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('payment_status', 'paid')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            // Format data for frontend
            $formattedData = $salesData->map(function ($item) use ($groupBy) {
                $date = Carbon::parse($item->period);
                return [
                    'name' => $groupBy === 'day' ? $date->format('M d') : $date->format('M Y'),
                    'sales' => (float) $item->sales,
                    'orders' => (int) $item->orders,
                    'date' => $item->period
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $formattedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch sales trend: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order status distribution
     */
    public function getOrderStatusDistribution(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 30);
            $startDate = Carbon::now()->subDays($dateRange);
            $endDate = Carbon::now();

            $statusData = Order::select('status', DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('status')
                ->get();

            $totalOrders = $statusData->sum('count');

            // Define colors for each status
            $statusColors = [
                'delivered' => '#10B981',
                'processing' => '#F59E0B',
                'shipped' => '#3B82F6',
                'cancelled' => '#EF4444',
                'pending' => '#8B5CF6',
                'packed' => '#06B6D4'
            ];

            $formattedData = $statusData->map(function ($item) use ($totalOrders, $statusColors) {
                $percentage = $totalOrders > 0 ? ($item->count / $totalOrders) * 100 : 0;
                return [
                    'name' => ucfirst($item->status),
                    'value' => round($percentage, 1),
                    'count' => (int) $item->count,
                    'color' => $statusColors[strtolower($item->status)] ?? '#6B7280'
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $formattedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch order status distribution: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top selling products
     */
    public function getTopProducts(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 30);
            $limit = $request->get('limit', 10);
            $startDate = Carbon::now()->subDays($dateRange);
            $endDate = Carbon::now();

            $topProducts = OrderItem::select(
                    'products.id',
                    'products.name',
                    'products.sku_code',
                    DB::raw('SUM(order_items.quantity) as total_sold'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
                )
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->where('orders.payment_status', 'paid')
                ->groupBy('products.id', 'products.name', 'products.sku_code')
                ->orderBy('total_sold', 'desc')
                ->limit($limit)
                ->get();

            $formattedData = $topProducts->map(function ($item, $index) {
                return [
                    'id' => $item->id,
                    'rank' => $index + 1,
                    'name' => $item->name,
                    'sku_code' => $item->sku_code,
                    'sales' => (int) $item->total_sold,
                    'revenue' => (float) $item->total_revenue
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $formattedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch top products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer analytics
     */
    public function getCustomerAnalytics(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 30);
            $startDate = Carbon::now()->subDays($dateRange);
            $endDate = Carbon::now();

            // Total customers
            $totalCustomers = User::count();

            // New customers in period
            $newCustomers = User::whereBetween('created_at', [$startDate, $endDate])->count();

            // Repeat customers (customers with more than 1 order)
            $repeatCustomers = User::whereHas('orders', function ($query) {
                $query->havingRaw('COUNT(*) > 1');
            })->count();

            // Average orders per customer
            $totalOrders = Order::count();
            $avgOrdersPerCustomer = $totalCustomers > 0 ? $totalOrders / $totalCustomers : 0;

            // Customer lifetime value
            $totalRevenue = Order::where('payment_status', 'paid')->sum('total_amount');
            $customerLifetimeValue = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;

            // Customer satisfaction (mock data - you can implement actual review system)
            $customerSatisfaction = 89; // This should come from your review system

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_customers' => $totalCustomers,
                    'new_customers' => $newCustomers,
                    'repeat_customers' => $repeatCustomers,
                    'repeat_customer_rate' => $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0,
                    'avg_orders_per_customer' => round($avgOrdersPerCustomer, 2),
                    'customer_lifetime_value' => round($customerLifetimeValue, 2),
                    'customer_satisfaction' => $customerSatisfaction
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch customer analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive report data
     */
    public function getComprehensiveReport(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 30);
            
            // Get all report data in one request
            $dashboardStats = $this->getDashboardStats($request)->getData()->data;
            $salesTrend = $this->getSalesTrend($request)->getData()->data;
            $orderStatus = $this->getOrderStatusDistribution($request)->getData()->data;
            $topProducts = $this->getTopProducts($request)->getData()->data;
            $customerAnalytics = $this->getCustomerAnalytics($request)->getData()->data;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'dashboard_stats' => $dashboardStats,
                    'sales_trend' => $salesTrend,
                    'order_status' => $orderStatus,
                    'top_products' => $topProducts,
                    'customer_analytics' => $customerAnalytics,
                    'generated_at' => Carbon::now()->toISOString(),
                    'date_range' => $dateRange
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate comprehensive report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory stock availability report
     */
    public function getStockAvailabilityReport(Request $request)
    {
        try {
            $lowStockThreshold = $request->get('low_stock_threshold', 10);
            
            // Get all products with their stock information
            $products = Product::select('id', 'name', 'sku_code', 'sizes', 'is_published')
                ->where('is_published', true)
                ->get();

            $stockReport = [];
            $totalProducts = 0;
            $lowStockProducts = 0;
            $outOfStockProducts = 0;
            $totalStockValue = 0;

            foreach ($products as $product) {
                $sizes = is_array($product->sizes) ? $product->sizes : json_decode($product->sizes, true);
                
                if (empty($sizes)) continue;

                foreach ($sizes as $size) {
                    $stock = (int)($size['stock'] ?? 0);
                    $mrp = (float)($size['mrp'] ?? 0);
                    $sellingPrice = (float)($size['selling_price'] ?? 0);
                    
                    $status = 'In Stock';
                    if ($stock == 0) {
                        $status = 'Out of Stock';
                        $outOfStockProducts++;
                    } elseif ($stock <= $lowStockThreshold) {
                        $status = 'Low Stock';
                        $lowStockProducts++;
                    }

                    $stockValue = $stock * $sellingPrice;
                    $totalStockValue += $stockValue;

                    $stockReport[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku_code' => $product->sku_code,
                        'size' => $size['size'],
                        'stock_quantity' => $stock,
                        'mrp' => $mrp,
                        'selling_price' => $sellingPrice,
                        'stock_value' => $stockValue,
                        'status' => $status,
                        'alert_level' => $stock <= $lowStockThreshold ? 'critical' : 'normal'
                    ];
                    
                    $totalProducts++;
                }
            }

            // Sort by stock quantity (lowest first for alerts)
            usort($stockReport, function($a, $b) {
                return $a['stock_quantity'] <=> $b['stock_quantity'];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'products' => $stockReport,
                    'summary' => [
                        'total_products' => $totalProducts,
                        'low_stock_products' => $lowStockProducts,
                        'out_of_stock_products' => $outOfStockProducts,
                        'in_stock_products' => $totalProducts - $lowStockProducts - $outOfStockProducts,
                        'total_stock_value' => round($totalStockValue, 2),
                        'low_stock_threshold' => $lowStockThreshold
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch stock availability report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fast-moving vs slow-moving items report
     */
    public function getMovingItemsReport(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 30);
            $startDate = Carbon::now()->subDays($dateRange);
            $endDate = Carbon::now();

            // Get product sales data
            $productSales = OrderItem::select(
                    'products.id',
                    'products.name',
                    'products.sku_code',
                    'order_items.size',
                    DB::raw('SUM(order_items.quantity) as total_sold'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue'),
                    DB::raw('COUNT(DISTINCT orders.id) as order_frequency')
                )
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->where('orders.payment_status', 'paid')
                ->groupBy('products.id', 'products.name', 'products.sku_code', 'order_items.size')
                ->orderBy('total_sold', 'desc')
                ->get();

            // Calculate movement categories
            $totalProducts = $productSales->count();
            $fastMovingThreshold = $totalProducts > 0 ? $productSales->avg('total_sold') * 1.5 : 0;
            $slowMovingThreshold = $totalProducts > 0 ? $productSales->avg('total_sold') * 0.5 : 0;

            $fastMoving = [];
            $slowMoving = [];
            $normalMoving = [];

            foreach ($productSales as $product) {
                $category = 'Normal Moving';
                if ($product->total_sold >= $fastMovingThreshold) {
                    $category = 'Fast Moving';
                    $fastMoving[] = $product;
                } elseif ($product->total_sold <= $slowMovingThreshold) {
                    $category = 'Slow Moving';
                    $slowMoving[] = $product;
                } else {
                    $normalMoving[] = $product;
                }

                $product->movement_category = $category;
                $product->sales_velocity = $dateRange > 0 ? round($product->total_sold / $dateRange, 2) : 0;
            }

            // Get products with no sales (dead stock)
            $productsWithSales = $productSales->pluck('id')->unique();
            $deadStock = Product::select('id', 'name', 'sku_code', 'sizes')
                ->whereNotIn('id', $productsWithSales)
                ->where('is_published', true)
                ->get()
                ->map(function($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku_code' => $product->sku_code,
                        'total_sold' => 0,
                        'total_revenue' => 0,
                        'order_frequency' => 0,
                        'movement_category' => 'Dead Stock',
                        'sales_velocity' => 0
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'fast_moving' => $fastMoving,
                    'slow_moving' => $slowMoving,
                    'normal_moving' => $normalMoving,
                    'dead_stock' => $deadStock,
                    'summary' => [
                        'total_products_analyzed' => $totalProducts + $deadStock->count(),
                        'fast_moving_count' => count($fastMoving),
                        'slow_moving_count' => count($slowMoving),
                        'normal_moving_count' => count($normalMoving),
                        'dead_stock_count' => $deadStock->count(),
                        'fast_moving_threshold' => round($fastMovingThreshold, 2),
                        'slow_moving_threshold' => round($slowMovingThreshold, 2),
                        'analysis_period_days' => $dateRange
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch moving items report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory valuation report
     */
    public function getInventoryValuationReport(Request $request)
    {
        try {
            $products = Product::select('id', 'name', 'sku_code', 'sizes', 'category_id')
                ->with('category:id,name')
                ->where('is_published', true)
                ->get();

            $valuationReport = [];
            $categoryWiseValuation = [];
            $totalInventoryValue = 0;
            $totalCostValue = 0;
            $totalMRPValue = 0;

            foreach ($products as $product) {
                $sizes = is_array($product->sizes) ? $product->sizes : json_decode($product->sizes, true);
                
                if (empty($sizes)) continue;

                $categoryName = $product->category->name ?? 'Uncategorized';

                foreach ($sizes as $size) {
                    $stock = (int)($size['stock'] ?? 0);
                    $purchasePrice = (float)($size['purchase_price'] ?? 0);
                    $mrp = (float)($size['mrp'] ?? 0);
                    $sellingPrice = (float)($size['selling_price'] ?? 0);

                    if ($stock > 0) {
                        $costValue = $stock * $purchasePrice;
                        $mrpValue = $stock * $mrp;
                        $sellingValue = $stock * $sellingPrice;
                        $potentialProfit = $sellingValue - $costValue;
                        $profitMargin = $costValue > 0 ? (($sellingPrice - $purchasePrice) / $purchasePrice) * 100 : 0;

                        $valuationReport[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'sku_code' => $product->sku_code,
                            'category' => $categoryName,
                            'size' => $size['size'],
                            'stock_quantity' => $stock,
                            'purchase_price' => $purchasePrice,
                            'selling_price' => $sellingPrice,
                            'mrp' => $mrp,
                            'cost_value' => $costValue,
                            'selling_value' => $sellingValue,
                            'mrp_value' => $mrpValue,
                            'potential_profit' => $potentialProfit,
                            'profit_margin_percent' => round($profitMargin, 2)
                        ];

                        // Category-wise aggregation
                        if (!isset($categoryWiseValuation[$categoryName])) {
                            $categoryWiseValuation[$categoryName] = [
                                'category' => $categoryName,
                                'total_items' => 0,
                                'total_stock' => 0,
                                'cost_value' => 0,
                                'selling_value' => 0,
                                'mrp_value' => 0,
                                'potential_profit' => 0
                            ];
                        }

                        $categoryWiseValuation[$categoryName]['total_items']++;
                        $categoryWiseValuation[$categoryName]['total_stock'] += $stock;
                        $categoryWiseValuation[$categoryName]['cost_value'] += $costValue;
                        $categoryWiseValuation[$categoryName]['selling_value'] += $sellingValue;
                        $categoryWiseValuation[$categoryName]['mrp_value'] += $mrpValue;
                        $categoryWiseValuation[$categoryName]['potential_profit'] += $potentialProfit;

                        $totalInventoryValue += $sellingValue;
                        $totalCostValue += $costValue;
                        $totalMRPValue += $mrpValue;
                    }
                }
            }

            // Sort by selling value (highest first)
            usort($valuationReport, function($a, $b) {
                return $b['selling_value'] <=> $a['selling_value'];
            });

            $totalPotentialProfit = $totalInventoryValue - $totalCostValue;
            $overallProfitMargin = $totalCostValue > 0 ? (($totalPotentialProfit) / $totalCostValue) * 100 : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'products' => $valuationReport,
                    'category_wise' => array_values($categoryWiseValuation),
                    'summary' => [
                        'total_inventory_value' => round($totalInventoryValue, 2),
                        'total_cost_value' => round($totalCostValue, 2),
                        'total_mrp_value' => round($totalMRPValue, 2),
                        'total_potential_profit' => round($totalPotentialProfit, 2),
                        'overall_profit_margin' => round($overallProfitMargin, 2),
                        'total_products' => count($valuationReport),
                        'categories_count' => count($categoryWiseValuation)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch inventory valuation report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier report
     */
    public function getSupplierReport(Request $request)
    {
        try {
            // Get vendors (suppliers) with their purchase entries
            $suppliers = Vendor::select('id', 'vendor_name', 'email', 'phone_number', 'address_line1', 'city', 'state', 'status')
                ->with(['purchaseEntries' => function($query) {
                    $query->select('vendor_id', 'purchase_no', 'total_cost', 'status', 'payment_status', 'created_at')
                          ->orderBy('created_at', 'desc');
                }])
                ->get();

            $supplierReport = [];
            $totalSuppliers = 0;
            $totalPurchaseValue = 0;
            $activeSuppliersCount = 0;

            foreach ($suppliers as $supplier) {
                $purchaseEntries = $supplier->purchaseEntries;
                $totalPurchases = $purchaseEntries->count();
                $totalValue = $purchaseEntries->sum('total_cost');
                $pendingOrders = $purchaseEntries->where('payment_status', 'pending')->count();
                $completedOrders = $purchaseEntries->where('payment_status', 'paid')->count();
                $lastPurchaseDate = $purchaseEntries->first()?->created_at;

                $isActive = $totalPurchases > 0 && $lastPurchaseDate && 
                           Carbon::parse($lastPurchaseDate)->greaterThan(Carbon::now()->subDays(90));

                if ($isActive) {
                    $activeSuppliersCount++;
                }

                $supplierReport[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->vendor_name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone_number,
                    'address' => trim(($supplier->address_line1 ?? '') . ', ' . ($supplier->city ?? '') . ', ' . ($supplier->state ?? ''), ', '),
                    'total_purchases' => $totalPurchases,
                    'total_purchase_value' => $totalValue,
                    'pending_orders' => $pendingOrders,
                    'completed_orders' => $completedOrders,
                    'last_purchase_date' => $lastPurchaseDate ? Carbon::parse($lastPurchaseDate)->format('Y-m-d') : null,
                    'is_active' => $isActive,
                    'status' => $isActive ? 'Active' : 'Inactive',
                    'recent_purchases' => $purchaseEntries->take(5)->map(function($entry) {
                        return [
                            'purchase_no' => $entry->purchase_no,
                            'amount' => $entry->total_cost,
                            'status' => $entry->status,
                            'date' => $entry->created_at->format('Y-m-d')
                        ];
                    })
                ];

                $totalSuppliers++;
                $totalPurchaseValue += $totalValue;
            }

            // Sort by total purchase value (highest first)
            usort($supplierReport, function($a, $b) {
                return $b['total_purchase_value'] <=> $a['total_purchase_value'];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'suppliers' => $supplierReport,
                    'summary' => [
                        'total_suppliers' => $totalSuppliers,
                        'active_suppliers' => $activeSuppliersCount,
                        'inactive_suppliers' => $totalSuppliers - $activeSuppliersCount,
                        'total_purchase_value' => round($totalPurchaseValue, 2),
                        'average_purchase_per_supplier' => $totalSuppliers > 0 ? round($totalPurchaseValue / $totalSuppliers, 2) : 0,
                        'total_pending_orders' => collect($supplierReport)->sum('pending_orders'),
                        'total_completed_orders' => collect($supplierReport)->sum('completed_orders')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch supplier report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export report data (CSV format)
     */
    public function exportReport(Request $request)
    {
        try {
            $format = $request->get('format', 'csv');
            $reportType = $request->get('report_type', 'comprehensive');
            $dateRange = $request->get('date_range', 30);

            // Get report data based on type
            switch ($reportType) {
                case 'sales':
                    $data = $this->getDetailedSalesReport($request);
                    $title = "Detailed Sales Report";
                    break;
                case 'inventory':
                    $data = $this->getDetailedInventoryReport($request);
                    $title = "Detailed Inventory Report";
                    break;
                case 'customers':
                    $data = $this->getDetailedCustomerReport($request);
                    $title = "Detailed Customer Report";
                    break;
                case 'products':
                    $response = $this->getTopProducts($request);
                    $data = json_decode(json_encode($response->getData()->data), true);
                    $title = "Products Report";
                    break;
                case 'stock':
                    $response = $this->getStockAvailabilityReport($request);
                    $data = json_decode(json_encode($response->getData()->data), true);
                    $title = "Stock Availability Report";
                    break;
                case 'valuation':
                    $response = $this->getInventoryValuationReport($request);
                    $data = json_decode(json_encode($response->getData()->data), true);
                    $title = "Inventory Valuation Report";
                    break;
                case 'suppliers':
                    $response = $this->getSupplierReport($request);
                    $data = json_decode(json_encode($response->getData()->data), true);
                    $title = "Supplier Report";
                    break;
                default:
                    $comprehensiveResponse = $this->getComprehensiveReport($request);
                    $comprehensiveData = $comprehensiveResponse->getData()->data;
                    // Convert comprehensive data to a structured format
                    $data = $this->formatComprehensiveDataForExport($comprehensiveData);
                    $title = "Comprehensive Report";
            }

            $filename = strtolower(str_replace(' ', '_', $title)) . "_" . Carbon::now()->format('Y-m-d');

            if ($format === 'pdf') {
                return $this->generatePDFReport($data, $title, $filename, $dateRange);
            } else {
                // For CSV, return the data directly for frontend processing
                return response()->json([
                    'status' => 'success',
                    'data' => $data,
                    'filename' => $filename . '.csv',
                    'message' => 'Report data prepared for CSV export'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export detailed CSV reports with proper headers
     */
    public function exportDetailedCSV(Request $request)
    {
        try {
            $reportType = $request->get('report_type', 'sales');
            $dateRange = $request->get('date_range', 30);
            $format = $request->get('format', 'csv');

            switch ($reportType) {
                case 'sales':
                    $data = $this->getDetailedSalesReport($request);
                    $filename = "detailed_sales_report_" . Carbon::now()->format('Y-m-d') . ".csv";
                    break;
                case 'inventory':
                    $data = $this->getDetailedInventoryReport($request);
                    $filename = "detailed_inventory_report_" . Carbon::now()->format('Y-m-d') . ".csv";
                    break;
                case 'customers':
                    $data = $this->getDetailedCustomerReport($request);
                    $filename = "detailed_customer_report_" . Carbon::now()->format('Y-m-d') . ".csv";
                    break;
                default:
                    throw new \Exception('Invalid report type');
            }

            if ($format === 'csv') {
                return $this->generateCSVResponse($data, $filename);
            } else {
                // For PDF
                $title = ucwords(str_replace('_', ' ', $reportType)) . " Report";
                return $this->generatePDFReport($data, $title, str_replace('.csv', '', $filename), $dateRange);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export detailed report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate CSV response with proper headers
     */
    private function generateCSVResponse($data, $filename)
    {
        if (empty($data)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No data available for export'
            ], 400);
        }

        // Create CSV content with BOM for proper Excel encoding
        $csvContent = "\xEF\xBB\xBF"; // UTF-8 BOM
        
        // Add headers
        if (isset($data[0]) && is_array($data[0])) {
            $headers = array_keys($data[0]);
            $csvHeaders = [];
            
            foreach ($headers as $header) {
                $csvHeaders[] = '"' . str_replace('"', '""', ucwords(str_replace('_', ' ', $header))) . '"';
            }
            $csvContent .= implode(',', $csvHeaders) . "\r\n";
            
            // Add data rows
            foreach ($data as $row) {
                $csvRow = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    
                    // Convert to string and clean up
                    $value = (string)$value;
                    
                    // Always wrap in quotes for better CSV compatibility
                    $value = '"' . str_replace('"', '""', $value) . '"';
                    $csvRow[] = $value;
                }
                $csvContent .= implode(',', $csvRow) . "\r\n";
            }
        }

        // Return CSV as download
        return response($csvContent)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Generate PDF report
     */
    private function generatePDFReport($data, $title, $filename, $dateRange)
    {
        try {
            // Ensure data is an array
            if (!is_array($data)) {
                $data = json_decode(json_encode($data), true);
            }
            
            // Check if this is a comprehensive report (complex structure)
            $isComprehensiveReport = isset($data['dashboard_stats']) || isset($data['sales_trend']) || !$this->isSimpleArrayData($data);
            
            if ($isComprehensiveReport) {
                // Use the original simple template for comprehensive reports
                $reportData = [
                    'title' => $title,
                    'date' => Carbon::now()->format('F d, Y'),
                    'dateRange' => $dateRange . ' days',
                    'data' => $data,
                    'company' => 'Lia Fashions'
                ];

                $pdf = Pdf::loadView('reports.simple-pdf-template', $reportData);
            } else {
                // Use detailed template for simple array data (detailed reports)
                $mainData = [];
                $summaryData = [];
                $summaryStarted = false;
                
                foreach ($data as $row) {
                    if (is_array($row)) {
                        // Check if this is a summary row
                        $firstValue = reset($row);
                        if ($firstValue === '--- SUMMARY ---') {
                            $summaryStarted = true;
                            continue;
                        }
                        
                        if ($summaryStarted) {
                            // Extract summary information
                            $summaryKey = reset($row);
                            $summaryValue = next($row);
                            if ($summaryKey && $summaryValue !== '') {
                                $summaryData[$summaryKey] = $summaryValue;
                            }
                        } else {
                            $mainData[] = $row;
                        }
                    }
                }
                
                $reportData = [
                    'title' => $title,
                    'date' => Carbon::now()->format('F d, Y'),
                    'dateRange' => $dateRange . ' days',
                    'mainData' => $mainData,
                    'summaryData' => $summaryData,
                    'company' => 'Lia Fashions'
                ];

                $pdf = Pdf::loadView('reports.detailed-pdf-template', $reportData);
            }
            
            // Log the data structure for debugging
            \Log::info('PDF Data Structure: ', [
                'title' => $title,
                'isComprehensive' => $isComprehensiveReport,
                'dataType' => gettype($data)
            ]);

            // Set PDF options for better UTF-8 support
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'DejaVu Sans'
            ]);
            
            $pdf->setPaper('A4', 'portrait');
            return $pdf->download($filename . '.pdf');
            
        } catch (\Exception $e) {
            \Log::error('PDF Generation Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if data is a simple array structure (for detailed reports)
     */
    private function isSimpleArrayData($data)
    {
        if (!is_array($data) || empty($data)) {
            return false;
        }
        
        // Check if it's a simple array of arrays (like detailed reports)
        $firstItem = reset($data);
        if (!is_array($firstItem)) {
            return false;
        }
        
        // Check if the first item has scalar values (typical of detailed reports)
        $firstKeys = array_keys($firstItem);
        if (empty($firstKeys)) {
            return false;
        }
        
        $firstValue = $firstItem[$firstKeys[0]];
        return is_scalar($firstValue) || is_null($firstValue);
    }

    /**
     * Format comprehensive report data for export (PDF/CSV)
     */
    private function formatComprehensiveDataForExport($data)
    {
        try {
            // Convert stdClass to array if needed
            if (is_object($data)) {
                $data = json_decode(json_encode($data), true);
            }
            
            if (!is_array($data)) {
                return ['error' => 'Invalid data format'];
            }
            
            $formattedData = [];
            
            // Dashboard Stats
            if (isset($data['dashboard_stats'])) {
                $stats = $data['dashboard_stats'];
                $formattedData['dashboard_stats'] = [
                    ['Metric' => 'Total Orders', 'Value' => $stats['total_orders'] ?? 0, 'Change' => ($stats['orders_change'] ?? 0) . '%'],
                    ['Metric' => 'Total Revenue', 'Value' => 'Rs. ' . number_format($stats['total_revenue'] ?? 0, 2), 'Change' => ($stats['revenue_change'] ?? 0) . '%'],
                    ['Metric' => 'Average Order Value', 'Value' => 'Rs. ' . number_format($stats['average_order_value'] ?? 0, 2), 'Change' => ($stats['avg_order_value_change'] ?? 0) . '%'],
                    ['Metric' => 'New Customers', 'Value' => $stats['new_customers'] ?? 0, 'Change' => ($stats['new_customers_change'] ?? 0) . '%'],
                ];
            }
            
            // Sales Trend Data
            if (isset($data['sales_trend']) && is_array($data['sales_trend'])) {
                $formattedData['sales_trend'] = array_map(function($trend) {
                    if (is_object($trend)) {
                        $trend = json_decode(json_encode($trend), true);
                    }
                    return [
                        'Period' => $trend['name'] ?? 'Unknown',
                        'Sales' => 'Rs. ' . number_format($trend['sales'] ?? 0, 2),
                        'Orders' => $trend['orders'] ?? 0,
                        'Date' => $trend['date'] ?? ''
                    ];
                }, $data['sales_trend']);
            }
            
            // Order Status Distribution
            if (isset($data['order_status']) && is_array($data['order_status'])) {
                $formattedData['order_status'] = array_map(function($status) {
                    if (is_object($status)) {
                        $status = json_decode(json_encode($status), true);
                    }
                    return [
                        'Status' => $status['name'] ?? 'Unknown',
                        'Percentage' => ($status['value'] ?? 0) . '%',
                        'Count' => $status['count'] ?? 0
                    ];
                }, $data['order_status']);
            }
            
            // Top Products
            if (isset($data['top_products']) && is_array($data['top_products'])) {
                $formattedData['top_products'] = array_map(function($product) {
                    if (is_object($product)) {
                        $product = json_decode(json_encode($product), true);
                    }
                    return [
                        'Rank' => $product['rank'] ?? 'N/A',
                        'Product Name' => $product['name'] ?? 'Unknown',
                        'SKU Code' => $product['sku_code'] ?? 'N/A',
                        'Sales' => $product['sales'] ?? 0,
                        'Revenue' => 'Rs. ' . number_format($product['revenue'] ?? 0, 2)
                    ];
                }, array_slice($data['top_products'], 0, 10));
            }
            
            // Customer Analytics
            if (isset($data['customer_analytics'])) {
                $analytics = $data['customer_analytics'];
                $formattedData['customer_analytics'] = [
                    ['Metric' => 'Total Customers', 'Value' => $analytics['total_customers'] ?? 0],
                    ['Metric' => 'New Customers', 'Value' => $analytics['new_customers'] ?? 0],
                    ['Metric' => 'Repeat Customers', 'Value' => $analytics['repeat_customers'] ?? 0],
                    ['Metric' => 'Repeat Customer Rate', 'Value' => ($analytics['repeat_customer_rate'] ?? 0) . '%'],
                    ['Metric' => 'Customer Lifetime Value', 'Value' => 'Rs. ' . number_format($analytics['customer_lifetime_value'] ?? 0, 2)],
                    ['Metric' => 'Customer Satisfaction', 'Value' => ($analytics['customer_satisfaction'] ?? 0) . '%'],
                    ['Metric' => 'Avg Orders per Customer', 'Value' => $analytics['avg_orders_per_customer'] ?? 0],
                ];
            }
            
            return $formattedData;
        } catch (\Exception $e) {
            \Log::error('Error formatting comprehensive data: ' . $e->getMessage());
            \Log::error('Data type: ' . gettype($data));
            \Log::error('Data content: ' . print_r($data, true));
            return ['error' => 'Unable to format comprehensive report data'];
        }
    }

    /**
     * Format comprehensive report data for PDF generation (legacy method)
     */
    private function formatComprehensiveDataForPDF($data)
    {
        try {
            // Convert stdClass to array if needed
            if (is_object($data)) {
                $data = json_decode(json_encode($data), true);
            }
            
            if (!is_array($data)) {
                return ['error' => 'Invalid data format'];
            }
            
            $formattedData = [];
            
            // Dashboard Stats
            if (isset($data['dashboard_stats'])) {
                $stats = $data['dashboard_stats'];
                $formattedData['dashboard_stats'] = [
                    ['Metric' => 'Total Orders', 'Value' => $stats['total_orders'] ?? 0, 'Change' => ($stats['orders_change'] ?? 0) . '%'],
                    ['Metric' => 'Total Revenue', 'Value' => 'Rs. ' . number_format($stats['total_revenue'] ?? 0, 2), 'Change' => ($stats['revenue_change'] ?? 0) . '%'],
                    ['Metric' => 'Average Order Value', 'Value' => 'Rs. ' . number_format($stats['average_order_value'] ?? 0, 2), 'Change' => ($stats['avg_order_value_change'] ?? 0) . '%'],
                    ['Metric' => 'New Customers', 'Value' => $stats['new_customers'] ?? 0, 'Change' => ($stats['new_customers_change'] ?? 0) . '%'],
                ];
            }
            
            // Top Products
            if (isset($data['top_products']) && is_array($data['top_products'])) {
                $formattedData['top_products'] = array_map(function($product) {
                    // Convert product to array if it's an object
                    if (is_object($product)) {
                        $product = json_decode(json_encode($product), true);
                    }
                    return [
                        'Rank' => $product['rank'] ?? 'N/A',
                        'Product Name' => $product['name'] ?? 'Unknown',
                        'SKU Code' => $product['sku_code'] ?? 'N/A',
                        'Sales' => $product['sales'] ?? 0,
                        'Revenue' => 'Rs. ' . number_format($product['revenue'] ?? 0, 2)
                    ];
                }, array_slice($data['top_products'], 0, 10));
            }
            
            // Customer Analytics
            if (isset($data['customer_analytics'])) {
                $analytics = $data['customer_analytics'];
                $formattedData['customer_analytics'] = [
                    ['Metric' => 'Total Customers', 'Value' => $analytics['total_customers'] ?? 0],
                    ['Metric' => 'New Customers', 'Value' => $analytics['new_customers'] ?? 0],
                    ['Metric' => 'Repeat Customers', 'Value' => $analytics['repeat_customers'] ?? 0],
                    ['Metric' => 'Repeat Customer Rate', 'Value' => ($analytics['repeat_customer_rate'] ?? 0) . '%'],
                    ['Metric' => 'Customer Lifetime Value', 'Value' => 'Rs. ' . number_format($analytics['customer_lifetime_value'] ?? 0, 2)],
                ];
            }
            
            return $formattedData;
        } catch (\Exception $e) {
            \Log::error('Error formatting comprehensive data: ' . $e->getMessage());
            \Log::error('Data type: ' . gettype($data));
            \Log::error('Data content: ' . print_r($data, true));
            return ['error' => 'Unable to format comprehensive report data'];
        }
    }

    /**
     * Get detailed sales report with all order information
     */
    private function getDetailedSalesReport($request)
    {
        try {
            $dateRange = $request->get('date_range', 30);
            $startDate = Carbon::now()->subDays($dateRange);
            $endDate = Carbon::now();

            \Log::info('Sales Report - Date Range: ' . $startDate . ' to ' . $endDate);

            // Get detailed order data - try with and without date filter
            $ordersQuery = Order::with(['items.product', 'user']);
            
            // If no orders in date range, get all orders for testing
            $ordersInRange = $ordersQuery->clone()->whereBetween('created_at', [$startDate, $endDate])->count();
            
            if ($ordersInRange == 0) {
                \Log::info('No orders in date range, fetching all orders');
                $orders = Order::with(['items.product', 'user'])
                    ->orderBy('created_at', 'desc')
                    ->limit(100) // Limit for performance
                    ->get();
            } else {
                $orders = $ordersQuery->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            \Log::info('Found ' . $orders->count() . ' orders');

            $salesData = [];
            $totalOrders = 0;
            $totalRevenue = 0;

            if ($orders->isEmpty()) {
                // Return sample data if no orders found
                $salesData[] = [
                    'order_id' => 'SAMPLE-001',
                    'date_time' => Carbon::now()->format('Y-m-d H:i:s'),
                    'customer_name' => 'Sample Customer',
                    'customer_contact' => 'sample@example.com / +91-9999999999',
                    'products_purchased' => 'Sample Product (Size: M) (Qty: 1)',
                    'total_quantity' => 1,
                    'total_order_value' => 'Rs. 1,000.00',
                    'discount_applied' => 'Rs. 0.00',
                    'payment_method' => 'Online',
                    'payment_status' => 'Paid',
                    'order_status' => 'Delivered'
                ];
                $totalOrders = 1;
                $totalRevenue = 1000;
            } else {
                foreach ($orders as $order) {
                    $products = [];
                    $totalQuantity = 0;
                    
                    if ($order->items && $order->items->count() > 0) {
                        foreach ($order->items as $item) {
                            $productName = $item->product ? $item->product->name : 'Unknown Product';
                            $size = $item->size ? ' (Size: ' . $item->size . ')' : '';
                            $products[] = $productName . $size . ' (Qty: ' . $item->quantity . ')';
                            $totalQuantity += $item->quantity;
                        }
                    } else {
                        $products[] = 'No items found';
                    }

                    // Get customer info
                    $customerName = 'Guest Customer';
                    $customerContact = '';
                    if ($order->user) {
                        $customerName = $order->user->name;
                        $phone = $order->user->phone ?? $order->phone ?? '';
                        $email = $order->user->email ?? $order->email ?? '';
                        if ($phone) $customerContact = $phone;
                        if ($email && $phone) $customerContact = $email . ' / ' . $phone;
                        elseif ($email) $customerContact = $email;
                    } elseif ($order->phone || $order->email) {
                        $customerContact = ($order->email ?? '') . ($order->phone ? ' / ' . $order->phone : '');
                    }

                    $salesData[] = [
                        'order_id' => $order->order_number ?? ('ORD-' . $order->id),
                        'date_time' => $order->created_at->format('Y-m-d H:i:s'),
                        'customer_name' => $customerName,
                        'customer_contact' => $customerContact ?: 'No contact info',
                        'products_purchased' => implode(', ', $products),
                        'total_quantity' => $totalQuantity,
                        'total_order_value' => 'Rs. ' . number_format($order->total_amount, 2),
                        'discount_applied' => 'Rs. ' . number_format($order->discount_amount ?? 0, 2),
                        'payment_method' => $order->payment_method ?? 'Not specified',
                        'payment_status' => ucfirst($order->payment_status ?? 'pending'),
                        'order_status' => ucfirst($order->status ?? 'pending')
                    ];

                    $totalOrders++;
                    $totalRevenue += $order->total_amount;
                }
            }

            // Add summary rows
            $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
            
            $salesData[] = [
                'order_id' => '--- SUMMARY ---',
                'date_time' => '',
                'customer_name' => '',
                'customer_contact' => '',
                'products_purchased' => '',
                'total_quantity' => '',
                'total_order_value' => '',
                'discount_applied' => '',
                'payment_method' => '',
                'payment_status' => '',
                'order_status' => ''
            ];
            
            $salesData[] = [
                'order_id' => 'Total Orders',
                'date_time' => $totalOrders,
                'customer_name' => '',
                'customer_contact' => '',
                'products_purchased' => '',
                'total_quantity' => '',
                'total_order_value' => '',
                'discount_applied' => '',
                'payment_method' => '',
                'payment_status' => '',
                'order_status' => ''
            ];
            
            $salesData[] = [
                'order_id' => 'Total Revenue',
                'date_time' => 'Rs. ' . number_format($totalRevenue, 2),
                'customer_name' => '',
                'customer_contact' => '',
                'products_purchased' => '',
                'total_quantity' => '',
                'total_order_value' => '',
                'discount_applied' => '',
                'payment_method' => '',
                'payment_status' => '',
                'order_status' => ''
            ];
            
            $salesData[] = [
                'order_id' => 'Average Order Value',
                'date_time' => 'Rs. ' . number_format($avgOrderValue, 2),
                'customer_name' => '',
                'customer_contact' => '',
                'products_purchased' => '',
                'total_quantity' => '',
                'total_order_value' => '',
                'discount_applied' => '',
                'payment_method' => '',
                'payment_status' => '',
                'order_status' => ''
            ];

            \Log::info('Sales report generated with ' . count($salesData) . ' rows');
            return $salesData;
        } catch (\Exception $e) {
            \Log::error('Sales Report Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Return sample data on error
            return [
                [
                    'order_id' => 'ERROR-SAMPLE',
                    'date_time' => Carbon::now()->format('Y-m-d H:i:s'),
                    'customer_name' => 'Error - No Data Available',
                    'products_purchased' => 'Please check database connection',
                    'total_quantity' => 0,
                    'total_order_value' => '0.00',
                    'discount_applied' => '0.00',
                    'payment_method' => 'N/A',
                    'order_status' => 'Error'
                ]
            ];
        }
    }

    /**
     * Get detailed inventory report
     */
    private function getDetailedInventoryReport($request)
    {
        try {
            \Log::info('Generating inventory report');
            
            $products = Product::with(['category'])
                ->where('is_published', true)
                ->get();

            \Log::info('Found ' . $products->count() . ' published products');

            $inventoryData = [];
            $totalProducts = 0;
            $outOfStockCount = 0;
            $lowStockCount = 0;
            $lowStockThreshold = 10;

            if ($products->isEmpty()) {
                // Return sample data if no products found
                $inventoryData[] = [
                    'product_id' => 'SAMPLE-001',
                    'product_name' => 'Sample Product',
                    'category' => 'Sample Category',
                    'sku_code' => 'SKU-001',
                    'size' => 'M',
                    'current_stock' => 25,
                    'stock_status' => 'In Stock',
                    'reorder_level' => 10,
                    'mrp' => 'Rs. 2,000.00',
                    'selling_price' => 'Rs. 1,500.00',
                    'stock_value' => 'Rs. 37,500.00',
                    'last_restock_date' => Carbon::now()->format('Y-m-d')
                ];
                $totalProducts = 1;
            } else {
                foreach ($products as $product) {
                    $sizes = null;
                    
                    // Handle different size data formats
                    if (is_string($product->sizes)) {
                        $sizes = json_decode($product->sizes, true);
                    } elseif (is_array($product->sizes)) {
                        $sizes = $product->sizes;
                    }
                    
                    \Log::info('Product: ' . $product->name . ', Sizes: ' . json_encode($sizes));
                    
                    if (empty($sizes) || !is_array($sizes)) {
                        // If no sizes, create a default entry
                        $inventoryData[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'category' => $product->category ? $product->category->name : 'Uncategorized',
                            'sku_code' => $product->sku_code ?? 'AUTO-' . $product->id,
                            'size' => 'One Size',
                            'current_stock' => 0,
                            'stock_status' => 'Out of Stock',
                            'reorder_level' => $lowStockThreshold,
                            'mrp' => 'Rs. 0.00',
                            'selling_price' => 'Rs. 0.00',
                            'stock_value' => 'Rs. 0.00',
                            'last_restock_date' => $product->updated_at->format('Y-m-d')
                        ];
                        $totalProducts++;
                        $outOfStockCount++;
                        continue;
                    }

                    foreach ($sizes as $size) {
                        $stock = 0;
                        $sizeLabel = 'Unknown';
                        
                        // Handle different size array structures
                        if (is_array($size)) {
                            $stock = (int)($size['stock'] ?? $size['quantity'] ?? 0);
                            $sizeLabel = $size['size'] ?? $size['name'] ?? 'Unknown';
                        } else {
                            $sizeLabel = (string)$size;
                        }
                        
                        if ($stock == 0) {
                            $outOfStockCount++;
                        } elseif ($stock <= $lowStockThreshold) {
                            $lowStockCount++;
                        }

                        // Determine stock status
                        $stockStatus = 'In Stock';
                        if ($stock == 0) {
                            $stockStatus = 'Out of Stock';
                        } elseif ($stock <= $lowStockThreshold) {
                            $stockStatus = 'Low Stock';
                        }

                        // Get pricing info if available
                        $mrp = 0;
                        $sellingPrice = 0;
                        if (is_array($size)) {
                            $mrp = $size['mrp'] ?? $size['price'] ?? 0;
                            $sellingPrice = $size['selling_price'] ?? $size['sale_price'] ?? $mrp;
                        }

                        $inventoryData[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'category' => $product->category ? $product->category->name : 'Uncategorized',
                            'sku_code' => $product->sku_code ?? 'AUTO-' . $product->id,
                            'size' => $sizeLabel,
                            'current_stock' => $stock,
                            'stock_status' => $stockStatus,
                            'reorder_level' => $lowStockThreshold,
                            'mrp' => 'Rs. ' . number_format($mrp, 2),
                            'selling_price' => 'Rs. ' . number_format($sellingPrice, 2),
                            'stock_value' => 'Rs. ' . number_format($stock * $sellingPrice, 2),
                            'last_restock_date' => $product->updated_at->format('Y-m-d')
                        ];

                        $totalProducts++;
                    }
                }
            }

            // Add summary rows
            $inventoryData[] = [
                'product_id' => '--- SUMMARY ---',
                'product_name' => '',
                'category' => '',
                'sku_code' => '',
                'size' => '',
                'current_stock' => '',
                'stock_status' => '',
                'reorder_level' => '',
                'mrp' => '',
                'selling_price' => '',
                'stock_value' => '',
                'last_restock_date' => ''
            ];
            
            $inventoryData[] = [
                'product_id' => 'Total Products',
                'product_name' => $totalProducts,
                'category' => '',
                'sku_code' => '',
                'size' => '',
                'current_stock' => '',
                'stock_status' => '',
                'reorder_level' => '',
                'mrp' => '',
                'selling_price' => '',
                'stock_value' => '',
                'last_restock_date' => ''
            ];
            
            $inventoryData[] = [
                'product_id' => 'Out-of-Stock Count',
                'product_name' => $outOfStockCount,
                'category' => '',
                'sku_code' => '',
                'size' => '',
                'current_stock' => '',
                'stock_status' => '',
                'reorder_level' => '',
                'mrp' => '',
                'selling_price' => '',
                'stock_value' => '',
                'last_restock_date' => ''
            ];
            
            $inventoryData[] = [
                'product_id' => 'Low Stock Count',
                'product_name' => $lowStockCount,
                'category' => '',
                'sku_code' => '',
                'size' => '',
                'current_stock' => '',
                'stock_status' => '',
                'reorder_level' => '',
                'mrp' => '',
                'selling_price' => '',
                'stock_value' => '',
                'last_restock_date' => ''
            ];

            \Log::info('Inventory report generated with ' . count($inventoryData) . ' rows');
            return $inventoryData;
        } catch (\Exception $e) {
            \Log::error('Inventory Report Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Return sample data on error
            return [
                [
                    'product_id' => 'ERROR-001',
                    'product_name' => 'Error - No Data Available',
                    'category' => 'Error',
                    'sku_code' => 'ERR-001',
                    'size' => 'N/A',
                    'current_stock' => 0,
                    'reorder_level' => 0,
                    'supplier_name' => 'Error',
                    'last_restock_date' => Carbon::now()->format('Y-m-d')
                ]
            ];
        }
    }

    /**
     * Get detailed customer report
     */
    private function getDetailedCustomerReport($request)
    {
        try {
            $dateRange = $request->get('date_range', 30);
            $startDate = Carbon::now()->subDays($dateRange);

            \Log::info('Customer Report - Date Range: ' . $startDate . ' to now');

            // Get all customers with their orders and details
            $customers = User::with(['orders', 'details'])->get();

            \Log::info('Found ' . $customers->count() . ' customers');

            $customerData = [];
            $totalCustomers = 0;
            $returningCustomers = 0;
            $totalSpent = 0;

            if ($customers->isEmpty()) {
                // Return sample data if no customers found
                $customerData[] = [
                    'customer_id' => 'SAMPLE-001',
                    'name' => 'Sample Customer',
                    'email_phone' => 'sample@example.com / +91-9999999999',
                    'location' => 'Sample City',
                    'total_orders' => 3,
                    'total_spent' => 'Rs. 15,000.00',
                    'last_order_date' => Carbon::now()->format('Y-m-d'),
                    'loyalty_status' => 'Returning'
                ];
                $totalCustomers = 1;
                $returningCustomers = 1;
                $totalSpent = 15000;
            } else {
                foreach ($customers as $customer) {
                    // Get all orders for this customer
                    $allOrders = $customer->orders;
                    
                    // Filter orders by date range for spending calculation
                    $ordersInRange = $allOrders->filter(function($order) use ($startDate) {
                        return $order->created_at >= $startDate;
                    });
                    
                    $customerTotalSpent = $ordersInRange->sum('total_amount');
                    $totalOrdersInRange = $ordersInRange->count();
                    $totalOrdersAllTime = $allOrders->count();
                    $lastOrderDate = $allOrders->max('created_at');
                    
                    // Determine loyalty status based on all-time orders
                    $loyaltyStatus = $totalOrdersAllTime > 1 ? 'Returning' : 'New';
                    if ($loyaltyStatus === 'Returning') {
                        $returningCustomers++;
                    }

                    // Get better customer data
                    $phone = $customer->phone ?? '';
                    $email = $customer->email ?? '';
                    
                    // Get location from user details
                    $location = '';
                    if ($customer->details) {
                        $locationParts = array_filter([
                            $customer->details->city,
                            $customer->details->district,
                            $customer->details->state,
                            $customer->details->country
                        ]);
                        $location = implode(', ', $locationParts);
                        
                        \Log::info('Customer location data: ', [
                            'customer_id' => $customer->id,
                            'city' => $customer->details->city,
                            'district' => $customer->details->district,
                            'state' => $customer->details->state,
                            'country' => $customer->details->country,
                            'address1' => $customer->details->address1,
                            'final_location' => $location
                        ]);
                    } else {
                        \Log::info('No details found for customer: ' . $customer->id);
                    }
                    
                    // If no location from details, try to get from address or pincode
                    if (empty($location) && $customer->details) {
                        if ($customer->details->address1) {
                            $location = $customer->details->address1;
                        } elseif ($customer->details->pincode) {
                            $location = 'Pincode: ' . $customer->details->pincode;
                        }
                    }
                    
                    // If still no location, try to get from most recent order's shipping address
                    if (empty($location) && $allOrders->isNotEmpty()) {
                        $recentOrderWithAddress = $allOrders->where('shipping_address', '!=', null)->first();
                        if ($recentOrderWithAddress && $recentOrderWithAddress->shipping_address) {
                            // Parse shipping address if it's JSON
                            $shippingAddress = $recentOrderWithAddress->shipping_address;
                            if (is_string($shippingAddress)) {
                                $addressData = json_decode($shippingAddress, true);
                                if (is_array($addressData)) {
                                    $addressParts = array_filter([
                                        $addressData['city'] ?? null,
                                        $addressData['state'] ?? null,
                                        $addressData['country'] ?? null
                                    ]);
                                    if (!empty($addressParts)) {
                                        $location = implode(', ', $addressParts);
                                    }
                                } else {
                                    // If not JSON, use as is (but limit length)
                                    $location = substr($shippingAddress, 0, 100);
                                }
                            }
                        }
                    }
                    
                    // Format contact info better
                    $contactInfo = '';
                    if ($email && $phone) {
                        $contactInfo = $email . ' / ' . $phone;
                    } elseif ($email) {
                        $contactInfo = $email;
                    } elseif ($phone) {
                        $contactInfo = $phone;
                    } else {
                        $contactInfo = 'No contact info';
                    }

                    $customerData[] = [
                        'customer_id' => $customer->id,
                        'name' => $customer->name ?? 'Unknown Customer',
                        'email_phone' => $contactInfo,
                        'location' => !empty($location) ? $location : 'Location not available',
                        'total_orders' => $totalOrdersInRange,
                        'total_spent' => 'Rs. ' . number_format($customerTotalSpent, 2),
                        'last_order_date' => $lastOrderDate ? Carbon::parse($lastOrderDate)->format('Y-m-d') : 'Never',
                        'loyalty_status' => $loyaltyStatus
                    ];

                    $totalCustomers++;
                    $totalSpent += $customerTotalSpent;
                }
            }

            // Calculate averages
            $returningPercentage = $totalCustomers > 0 ? ($returningCustomers / $totalCustomers) * 100 : 0;
            $avgSpendPerCustomer = $totalCustomers > 0 ? $totalSpent / $totalCustomers : 0;

            // Add summary rows
            $customerData[] = [
                'customer_id' => '--- SUMMARY ---',
                'name' => '',
                'email_phone' => '',
                'location' => '',
                'total_orders' => '',
                'total_spent' => '',
                'last_order_date' => '',
                'loyalty_status' => ''
            ];
            
            $customerData[] = [
                'customer_id' => 'Total Customers',
                'name' => $totalCustomers,
                'email_phone' => '',
                'location' => '',
                'total_orders' => '',
                'total_spent' => '',
                'last_order_date' => '',
                'loyalty_status' => ''
            ];
            
            $customerData[] = [
                'customer_id' => 'Returning Customers %',
                'name' => number_format($returningPercentage, 1) . '%',
                'email_phone' => '',
                'location' => '',
                'total_orders' => '',
                'total_spent' => '',
                'last_order_date' => '',
                'loyalty_status' => ''
            ];
            
            $customerData[] = [
                'customer_id' => 'Avg Spend per Customer',
                'name' => 'Rs. ' . number_format($avgSpendPerCustomer, 2),
                'email_phone' => '',
                'location' => '',
                'total_orders' => '',
                'total_spent' => '',
                'last_order_date' => '',
                'loyalty_status' => ''
            ];

            \Log::info('Customer report generated with ' . count($customerData) . ' rows');
            return $customerData;
        } catch (\Exception $e) {
            \Log::error('Customer Report Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Return sample data on error
            return [
                [
                    'customer_id' => 'ERROR-001',
                    'name' => 'Error - No Data Available',
                    'email_phone' => 'error@example.com / N/A',
                    'location' => 'Error Location',
                    'total_orders' => 0,
                    'total_spent' => 'Rs. 0.00',
                    'last_order_date' => 'Never',
                    'loyalty_status' => 'Error'
                ]
            ];
        }
    }
}