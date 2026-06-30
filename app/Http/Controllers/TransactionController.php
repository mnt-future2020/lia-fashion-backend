<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function store(Request $request)
    {
        try {
            Log::info('Creating new transaction', [
                'request' => $request->except(['contactInfo', 'shippingAddress']),
                'contact_info' => array_keys($request->input('contactInfo', [])),
                'shipping_address' => array_keys($request->input('shippingAddress', []))
            ]);

            // Validate request data
            $validatedData = $request->validate([
                'paymentMethod' => 'required|string|in:online,cod',
                'totalAmount' => 'required|numeric|min:0',
                'summary.subtotal' => 'required|numeric|min:0',
                'summary.totalGst' => 'required|numeric|min:0',
                'contactInfo.fullName' => 'required|string|max:255',
                'contactInfo.mobile' => 'required|string|max:20',
                'shippingAddress' => 'required|array',
                'cartItems' => 'required|array'
            ]);

            // Validate stock availability before creating transaction
            $stockValidation = $this->validateStockAvailability($request->cartItems);
            if (!$stockValidation['isValid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $stockValidation['message']
                ], 400);
            }

            DB::beginTransaction();

            // Generate invoice number from settings
            $invoiceNumber = null;
            $setting = \App\Models\InvoiceSetting::where('is_active', true)->first();

            if ($setting) {
                $newSequence = $setting->last_sequence_number + 1;
                $fyStartYear = \Carbon\Carbon::parse($setting->financial_year_start)->format('y');
                $fyEndYear = \Carbon\Carbon::parse($setting->financial_year_end)->format('y');
                $invoiceNumber = $setting->prefix . $fyStartYear . $fyEndYear . str_pad($newSequence, 4, '0', STR_PAD_LEFT);

                // Update the last sequence number
                $setting->update(['last_sequence_number' => $newSequence]);
            } else {
                $invoiceNumber = 'INV-' . Str::random(10);
            }

            // Create transaction with explicit payment status
            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                // Generate a continuous order number
                'order_number' => 'ORD-' . date('Ymd') . '-' . str_pad((Transaction::max('id') + 1), 6, '0', STR_PAD_LEFT),
                'transaction_type' => 'Online',
                'payment_method' => $request->paymentMethod,
                'payment_status' => $request->paymentMethod === 'online' ? 'Pending' : 'Paid',
                'subtotal_amount' => $request->summary['subtotal'],
                'tax_amount' => $request->summary['totalGst'],
                'total_amount' => $request->totalAmount,
                'transaction_date' => now(),
                'customer_name' => $request->contactInfo['fullName'],
                'customer_phone' => $request->contactInfo['mobile'],
                'notes' => json_encode([
                    'shipping_address' => $request->shippingAddress,
                    'delivery_method' => $request->deliveryMethod ?? 'courier',
                    'coupon' => $request->appliedCoupon,
                    'shipping_charge' => $request->shippingCharge,
                    'bulk_discount' => $request->summary['totalBulkDiscount'] ?? 0
                ])
            ]);

            DB::commit();

            // Refresh to ensure we have the latest data
            $transaction = Transaction::findOrFail($transaction->id);

            Log::info('Transaction created successfully', [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->total_amount,
                'status' => $transaction->payment_status
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $transaction
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Transaction validation failed', [
                'errors' => $e->errors()
            ]);
            throw $e;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create transaction: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createOrder(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:1',
                'currency' => 'required|string|in:INR'
            ]);

            $order = $this->paymentService->createRazorpayOrder([
                'amount' => $request->amount * 100, // Convert to smallest currency unit (paise)
                'currency' => $request->currency,
                'notes' => [
                    'user_id' => auth()->id() ?? null,
                ]
            ]);

            return response()->json($order);
        } catch (\Exception $e) {
            Log::error('Order creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create order'], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        try {
            $request->validate([
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'required|string',
                'razorpay_signature' => 'required|string',
                'amount' => 'required|numeric'
            ]);

            $verified = $this->paymentService->verifyRazorpayPayment(
                $request->razorpay_payment_id,
                $request->razorpay_order_id,
                $request->razorpay_signature
            );

            if ($verified) {
                DB::beginTransaction();

                // Record payment transaction
                $paymentTransaction = PaymentTransaction::create([
                    'user_id' => auth()->id(),
                    'payment_id' => $request->razorpay_payment_id,
                    'order_id' => $request->razorpay_order_id,
                    'amount' => $request->amount,
                    'status' => 'completed',
                    'payment_method' => 'razorpay'
                ]);

                // Update original transaction status if exists
                if ($request->transaction_id) {
                    Transaction::where('id', $request->transaction_id)
                        ->update(['payment_status' => 'Paid']);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'transaction' => $paymentTransaction
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed'
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed'
            ], 500);
        }
    }

    public function getTransactionHistory()
    {
        try {
            $transactions = PaymentTransaction::with(['user'])
                ->where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($transactions);
        } catch (\Exception $e) {
            Log::error('Failed to fetch transaction history: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch transaction history'], 500);
        }
    }

    /**
     * Validate stock availability for cart items
     */
    private function validateStockAvailability($cartItems)
    {
        try {
            foreach ($cartItems as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                
                if (!$product) {
                    return [
                        'isValid' => false,
                        'message' => "Product not found: {$item['product_name']}"
                    ];
                }

                $sizes = is_array($product->sizes) ? $product->sizes : json_decode($product->sizes, true);
                
                if (empty($sizes)) {
                    return [
                        'isValid' => false,
                        'message' => "Product {$product->name} has no size configuration"
                    ];
                }

                $selectedSize = $item['size'] ?? null;
                $requestedQuantity = (int)($item['quantity'] ?? 1);

                if ($selectedSize) {
                    // Check stock for specific size
                    $sizeData = collect($sizes)->firstWhere('size', $selectedSize);
                    
                    if (!$sizeData) {
                        return [
                            'isValid' => false,
                            'message' => "Size {$selectedSize} not available for {$product->name}"
                        ];
                    }

                    $availableStock = (int)($sizeData['stock'] ?? 0);
                    
                    if ($availableStock < $requestedQuantity) {
                        return [
                            'isValid' => false,
                            'message' => "Insufficient stock for {$product->name} (Size: {$selectedSize}). Only {$availableStock} available, but {$requestedQuantity} requested."
                        ];
                    }

                    if ($availableStock === 0) {
                        return [
                            'isValid' => false,
                            'message' => "{$product->name} (Size: {$selectedSize}) is out of stock."
                        ];
                    }
                } else {
                    // Check total stock across all sizes
                    $totalStock = collect($sizes)->sum('stock');
                    
                    if ($totalStock < $requestedQuantity) {
                        return [
                            'isValid' => false,
                            'message' => "Insufficient total stock for {$product->name}. Only {$totalStock} available, but {$requestedQuantity} requested."
                        ];
                    }

                    if ($totalStock === 0) {
                        return [
                            'isValid' => false,
                            'message' => "{$product->name} is out of stock."
                        ];
                    }
                }
            }

            return ['isValid' => true];
        } catch (\Exception $e) {
            Log::error('Stock validation failed: ' . $e->getMessage());
            return [
                'isValid' => false,
                'message' => 'Stock validation failed. Please try again.'
            ];
        }
    }
}
