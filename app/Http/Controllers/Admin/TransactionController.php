<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\PosCustomer;
use App\Models\PosOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * Display a listing of the transactions.
     */
    public function index(Request $request)
    {
        try {
            $query = Transaction::query();

            // Apply filters
            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('invoice_number', 'like', "%$search%")
                      ->orWhere('order_number', 'like', "%$search%")
                      ->orWhere('customer_name', 'like', "%$search%")
                      ->orWhere('customer_phone', 'like', "%$search%");
                });
            }

            if ($request->has('date_from') && $request->has('date_to')) {
                $query->whereBetween('transaction_date', [$request->date_from, $request->date_to]);
            } else if ($request->has('date_from')) {
                $query->where('transaction_date', '>=', $request->date_from);
            } else if ($request->has('date_to')) {
                $query->where('transaction_date', '<=', $request->date_to);
            }

            // Sorting
            $sortField = $request->input('sort_field', 'transaction_date');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $transactions = $query->paginate($request->input('per_page', 10));

            return response()->json([
                'status' => 'success',
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created transaction.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'invoice_number' => 'required|string',
                'order_number' => 'required|string',
                'transaction_type' => 'required|in:POS,Online',
                'payment_method' => 'required|string',
                'payment_status' => 'required|in:Paid,Pending,Failed',
                'subtotal_amount' => 'required|numeric|min:0',
                'tax_amount' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'transaction_date' => 'required|date',
                'pos_customer_id' => 'nullable|exists:pos_customers,id',
                'customer_name' => 'nullable|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $transaction = Transaction::create($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction created successfully',
                'data' => $transaction
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transaction with items.
     */
    public function show($id)
    {
        try {
              // Accept either numeric transaction ID or order/invoice identifier
            if (is_numeric($id)) {
                $transaction = Transaction::findOrFail($id);
            } else {
                $transaction = Transaction::where('order_number', $id)
                    ->orWhere('invoice_number', $id)
                    ->firstOrFail();
            }

            // Get order items for this specific transaction with all necessary fields including SKU code
            $orderItems = PosOrderItem::where('transaction_id', $transaction->id)
                ->leftJoin('products', 'pos_order_items.product_id', '=', 'products.id')
                ->select([
                    'pos_order_items.id',
                    'pos_order_items.product_id',
                    'pos_order_items.product_name',
                    'pos_order_items.quantity',
                    'pos_order_items.price',
                    'pos_order_items.size',
                    'pos_order_items.color',
                    'pos_order_items.tax_percentage',
                    'pos_order_items.tax_amount',
                    'pos_order_items.subtotal',
                    'pos_order_items.created_at',
                    'products.sku_code'
                ])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'transaction' => $transaction,
                    'items' => $orderItems
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the transaction status.
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_status' => 'required|in:Paid,Pending,Failed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $transaction = Transaction::findOrFail($id);
            $transaction->payment_status = $request->payment_status;
            $transaction->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction status updated successfully',
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update transaction status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction statistics.
     */
    public function getStatistics()
    {
        try {
            $totalTransactions = Transaction::count();
            $totalAmount = Transaction::sum('total_amount');
            $posTransactions = Transaction::where('transaction_type', 'POS')->count();
            $onlineTransactions = Transaction::where('transaction_type', 'Online')->count();

            $recentTransactions = Transaction::orderBy('transaction_date', 'desc')
                ->take(5)
                ->get();

            $monthlyStats = Transaction::selectRaw('MONTH(transaction_date) as month, SUM(total_amount) as total')
                ->whereYear('transaction_date', date('Y'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_transactions' => $totalTransactions,
                    'total_amount' => $totalAmount,
                    'pos_transactions' => $posTransactions,
                    'online_transactions' => $onlineTransactions,
                    'recent_transactions' => $recentTransactions,
                    'monthly_stats' => $monthlyStats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve transaction statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert a pending Razorpay transaction to an order.
     */
    public function convertToOrder($id)
    {
        try {
            DB::beginTransaction();

            // Find the transaction
            $transaction = Transaction::findOrFail($id);

            // Validate that this is a pending Razorpay transaction
            if ($transaction->payment_method !== 'Razorpay' || $transaction->payment_status !== 'Pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only pending Razorpay transactions can be converted to orders'
                ], 400);
            }

            // Check if order already exists for this transaction
            $existingOrder = Order::where('order_number', $transaction->order_number)->first();
            if ($existingOrder) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'An order already exists for this transaction'
                ], 400);
            }

            // Get the payment transaction to retrieve cart items
            $paymentTransaction = PaymentTransaction::where('transaction_id', $transaction->id)->first();

            if (!$paymentTransaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment transaction not found'
                ], 404);
            }

            // Get cart items from metadata
            $metadata = json_decode($paymentTransaction->metadata, true);
            $cartItems = $metadata['items'] ?? [];

            if (empty($cartItems)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No items found in transaction'
                ], 400);
            }

                        // Parse transaction notes for additional details
            $notes = $transaction->notes;
            $shipping_cost = 0;
            $subtotal = 0;
            $tax = 0;
            $shippingAddressData = null;

            if ($notes) {
                $notesArr = is_array($notes) ? $notes : json_decode($notes, true);
                if (is_array($notesArr)) {
                    $shipping_cost = $notesArr['shipping_charge'] ?? 0;
                    $subtotal = $notesArr['subtotal'] ?? 0;
                    $tax = $notesArr['tax'] ?? 0;
                    $shippingAddressData = $notesArr['shipping_address'] ?? null;
                }
            }

            // If notes don't have the values, calculate from transaction
            if (!$subtotal) {
                $subtotal = $transaction->subtotal_amount;
            }
            if (!$tax) {
                $tax = $transaction->tax_amount;
            }

            // Check if Shiprocket is active
            $shiprocketActive = \App\Models\ShiprocketSetting::where('is_active', true)->exists();

            // Create shipping address JSON using data from notes if available
            $shippingAddress = json_encode([
                'name' => $transaction->customer_name ?: 'Customer',
                'phone' => $transaction->customer_phone ?: '',
                'email' => '', // Will need to be updated manually
                'address' => $shippingAddressData['addressLine1'] ?? 'Address to be updated',
                'city' => $shippingAddressData['city'] ?? 'City to be updated',
                'state' => $shippingAddressData['state'] ?? 'State to be updated',
                'country' => $shippingAddressData['country'] ?? 'India',
                'pin_code' => $shippingAddressData['pincode'] ?? '000000',
                'district' => $shippingAddressData['district'] ?? null,
                'notes' => 'Converted from pending Razorpay transaction'
            ]);

            // Create the order
            $order = Order::create([
                'user_id' => $paymentTransaction->user_id,
                'order_number' => $transaction->order_number,
                'total_amount' => $transaction->total_amount,
                'shipping_cost' => $shipping_cost,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'status' => 'processing',
                'payment_id' => null, // Will be updated when payment is processed
                'payment_status' => 'pending',
                'order_type' => $shiprocketActive ? 'shiprocket' : 'manual',
                'notes' => 'Converted from pending Razorpay transaction' . ($shippingAddressData ? ' - Shipping address imported' : ' - Please update shipping details'),
                'shipping_address' => $shippingAddress,
                'phone' => $transaction->customer_phone ?: 'Phone to be updated',
                'email' => 'email@example.com' // Default email, to be updated manually
            ]);

            // Create order items
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'size' => $item['size'] ?? null,
                    'color' => $item['color'] ?? null
                ]);
            }

            // Update transaction status to indicate it has been converted
            // Since payment_status is enum with limited values, we keep it as 'Pending'
            // and store conversion info in notes
            $transaction->update([
                'payment_status' => 'Pending', // Keep as Pending since order still needs processing
                'notes' => json_encode(array_merge(
                    is_array($notes) ? $notes : (json_decode($notes, true) ?? []),
                    [
                        'converted_to_order_at' => now()->toISOString(),
                        'order_id' => $order->id,
                        'converted_by' => 'admin',
                        'status' => 'converted_to_order'
                    ]
                ))
            ]);

            DB::commit();

            Log::info('Transaction converted to order successfully', [
                'transaction_id' => $transaction->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction successfully converted to order',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'transaction_id' => $transaction->id
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to convert transaction to order: ' . $e->getMessage(), [
                'transaction_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to convert transaction to order: ' . $e->getMessage()
            ], 500);
        }
    }
}
