<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\PaymentTransaction;
use App\Models\RazorpayOrderItem;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;

class RazorpayController extends Controller
{
    private $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function createOrder(Request $request)
    {
        DB::beginTransaction();
        try {
            // Log incoming request data for debugging
            Log::info('Razorpay createOrder request:', [
                'transaction_id' => $request->transaction_id,
                'all_data' => $request->all()
            ]);

            // Validate request data
            $validator = Validator::make($request->all(), [
                'transaction_id' => 'required|integer|min:1',
                'cart_data' => 'required|array',
                'cart_data.items' => 'required|array'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed:', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Try to find the transaction with multiple attempts
            $maxAttempts = 3;
            $attempt = 1;
            $transaction = null;

            while ($attempt <= $maxAttempts) {
                $transaction = Transaction::find($request->transaction_id);

                if ($transaction) {
                    break;
                }

                Log::info("Transaction not found on attempt {$attempt}, waiting before retry...");
                sleep(1); // Wait for 1 second before retry
                $attempt++;
            }

            if (!$transaction) {
                Log::error('Transaction not found after all attempts:', [
                    'transaction_id' => $request->transaction_id,
                    'attempts' => $maxAttempts
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found',
                    'errors' => [
                        'transaction_id' => ['The transaction could not be found after multiple attempts.']
                    ]
                ], 404);
            }

            // Log found transaction details
            Log::info('Found transaction:', [
                'id' => $transaction->id,
                'amount' => $transaction->total_amount,
                'status' => $transaction->payment_status
            ]);

            // Calculate amount in paise
            $amount = round($transaction->total_amount * 100);

            // Create Razorpay order
            $orderData = [
                'amount' => $amount,
                'currency' => 'INR',
                'notes' => [
                    'transaction_id' => $transaction->id,
                    'order_number' => $transaction->order_number
                ]
            ];

            $order = $this->paymentService->createRazorpayOrder($orderData);

            // Get coupon ID if a coupon was applied
            $couponId = null;
            if (!empty($request->cart_data['coupon_id'])) {
                $couponId = $request->cart_data['coupon_id'];
            }

            // Create payment transaction record with cart data and coupon info
            $paymentTransaction = PaymentTransaction::create([
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'razorpay_order_id' => $order['id'],
                'amount' => $transaction->total_amount,
                'status' => 'pending',
                'metadata' => json_encode([
                    'order_number' => $transaction->order_number,
                    'customer_name' => $transaction->customer_name,
                    'items' => $request->cart_data['items'],
                    'coupon_id' => $couponId
                ])
            ]);

            // Update transaction with payment details
            $transaction->update([
                'payment_transaction_id' => $paymentTransaction->id,
                'transaction_type' => 'Online',
                'payment_method' => 'Razorpay',
                'payment_status' => 'Pending'
            ]);

            DB::commit();

            Log::info('Razorpay order created successfully', [
                'order_id' => $order['id'],
                'transaction_id' => $transaction->id
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'key' => \App\Models\PaymentGatewaySetting::where('gateway_name', 'razorpay')
                        ->where('is_active', true)
                        ->value('key_id') ?? config('services.razorpay.key'),
                    'order_id' => $order['id'],
                    'amount' => $amount,
                    'currency' => 'INR',
                    'name' => config('app.name', 'Your Store'),
                    'description' => 'Order #' . $transaction->order_number,
                    'prefill' => [
                        'name' => $transaction->customer_name ?? '',
                        'email' => auth()->user()->email ?? '',
                        'contact' => $transaction->customer_phone ?? ''
                    ],
                    'theme' => [
                        'color' => '#2563eb'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Razorpay order creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment initialization failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'razorpay_order_id' => 'required|string',
                'razorpay_payment_id' => 'required|string',
                'razorpay_signature' => 'required|string',
                'transaction_id' => 'required|exists:transactions,id',
                'shipping_details' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify payment signature
            if ($this->paymentService->verifyRazorpayPayment(
                $request->razorpay_payment_id,
                $request->razorpay_order_id,
                $request->razorpay_signature
            )) {
                DB::beginTransaction();
                try {
                    // Get payment transaction and order details
                    $paymentTransaction = PaymentTransaction::where('razorpay_order_id', $request->razorpay_order_id)
                        ->firstOrFail();

                    $transaction = Transaction::findOrFail($request->transaction_id);

                    // Get metadata with cart items and coupon info
                    $metadata = json_decode($paymentTransaction->metadata, true);
                    $cartItems = $metadata['items'] ?? [];
                    $couponId = $metadata['coupon_id'] ?? null;

                    // Update transaction and payment details atomically
                    DB::transaction(function() use ($transaction, $paymentTransaction, $request) {
                        // Update payment transaction
                        $paymentTransaction->updatePaymentDetails(
                            $request->razorpay_payment_id,
                            $request->razorpay_signature
                        );

                        // Update transaction
                        $transaction->update([
                            'payment_status' => 'Paid',
                            'transaction_type' => 'Online',
                            'payment_method' => 'Razorpay'
                        ]);
                    });

                    // Create order record
                    $metadata = json_decode($paymentTransaction->metadata, true);
                    $cartItems = $metadata['items'] ?? [];

                    // Check if Shiprocket is active
                    $shiprocketActive = \App\Models\ShiprocketSetting::where('is_active', true)->exists();

                    // Parse shipping cost, subtotal, and tax from transaction notes JSON
                    $notes = $transaction->notes;
                    $shipping_cost = 0;
                    $subtotal = 0;
                    $tax = 0;
                    if ($notes) {
                        $notesArr = is_array($notes) ? $notes : json_decode($notes, true);
                        if (is_array($notesArr)) {
                            $shipping_cost = $notesArr['shipping_charge'] ?? 0;
                            $subtotal = $notesArr['subtotal'] ?? 0;
                            $tax = $notesArr['tax'] ?? 0;
                        }
                    }

                    // Try to find existing order first
                    $existingOrder = Order::where('payment_id', $request->razorpay_payment_id)
                        ->orWhere('order_number', $transaction->order_number)
                        ->first();

                    if ($existingOrder) {
                        // Update payment status if needed
                        if ($existingOrder->payment_status !== 'paid') {
                            $existingOrder->update([
                                'payment_status' => 'paid',
                                'payment_id' => $request->razorpay_payment_id
                            ]);
                        }
                        $order = $existingOrder;
                    } else {
                        // Generate a unique order number
                        $orderNumber = $this->generateUniqueOrderNumber();

                        $order = Order::create([
                            'user_id' => auth()->id(),
                            'order_number' => $orderNumber,
                            'total_amount' => $transaction->total_amount,
                            'shipping_cost' => $shipping_cost,
                            'subtotal' => $subtotal,
                            'tax' => $tax,
                            'status' => 'processing',
                            'payment_id' => $request->razorpay_payment_id,
                            'payment_status' => 'paid',
                            'order_type' => $shiprocketActive ? 'shiprocket' : 'manual',
                            'notes' => $request->shipping_details['notes'] ?? null,
                            'shipping_address' => json_encode($request->shipping_details),
                            'phone' => $request->shipping_details['phone'],
                            'email' => $request->shipping_details['email']
                        ]);
                    }

                    // Process each cart item within the transaction
                    foreach ($cartItems as $item) {
                        // Create order item
                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                            'size' => $item['size'] ?? null,
                            'color' => $item['color'] ?? null
                        ]);

                        // Update product stock
                        $this->updateProductStock($item['product_id'], $item['quantity'], $item['size'] ?? null);
                    }

                    // Store each cart item as a RazorpayOrderItem
                    foreach ($cartItems as $item) {
                        RazorpayOrderItem::create([
                            'payment_transaction_id' => $paymentTransaction->id,
                            'product_id' => $item['product_id'],
                            'product_name' => $item['name'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['price'],
                            'total_price' => $item['quantity'] * $item['price'],
                            'color' => $item['color'] ?? null,
                            'size' => $item['size'] ?? null,
                            'metadata' => [
                                'tax_percentage' => $item['tax_percentage'] ?? 0,
                                'tax_amount' => $item['tax_amount'] ?? 0
                            ]
                        ]);
                    }

                    // Handle coupon usage if a coupon was applied
                    if ($couponId) {
                        DB::transaction(function() use ($couponId) {
                            $coupon = \App\Models\Coupon::lockForUpdate()->findOrFail($couponId);
                            $coupon->increment('usage_count');
                            $coupon->users()->attach(auth()->id());
                        });
                    }

                    // Add Shiprocket integration if active
                    try {
                        if ($shiprocketActive) {
                            $shiprocketService = new \App\Services\ShiprocketOrderService();

                            // Format shipping details
                            $formattedShippingDetails = [
                                'name' => $request->shipping_details['name'] ?? $transaction->customer_name,
                                'email' => $request->shipping_details['email'] ?? auth()->user()->email,
                                'phone' => $request->shipping_details['phone'] ?? $transaction->customer_phone,
                                'address' => $request->shipping_details['address'],
                                'city' => $request->shipping_details['city'],
                                'state' => $request->shipping_details['state'],
                                'country' => $request->shipping_details['country'] ?? 'India',
                                'pin_code' => $request->shipping_details['pin_code'],
                                'order_items' => array_map(function($item) {
                                    return [
                                        'name' => $item['name'] ?? $item['product_name'] ?? '',
                                        'sku' => (string)($item['product_id'] ?? ''),
                                        'units' => (int)($item['quantity'] ?? 1),
                                        'selling_price' => (string)($item['price'] ?? 0),
                                        'weight' => '0.5',
                                        'weight_unit' => 'kg'
                                    ];
                                }, $cartItems),
                                'payment_method' => 'prepaid',
                                'sub_total' => $transaction->total_amount,
                                'order_id' => $transaction->id,
                                'order_date' => now()->format('Y-m-d H:i'),
                            ];

                            // Create Shiprocket order
                            $shippingResult = $shiprocketService->createOrder(
                                $formattedShippingDetails,
                                $transaction->id
                            );

                            // Update transaction with shipping details
                            $transaction->update([
                                'shipping_status' => $shippingResult['status'],
                                'shipment_id' => $shippingResult['shipment_id'] ?? null,
                                'shipping_details' => json_encode([
                                    'data' => $shippingResult['data'] ?? null,
                                    'error' => $shippingResult['message'] ?? null,
                                    'shipping_provider' => 'shiprocket',
                                    'created_at' => now()
                                ])
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Log Shiprocket error but don't fail the transaction
                        Log::error('Shiprocket order creation failed', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'transaction_id' => $transaction->id
                        ]);

                        $shippingResult = [
                            'status' => 'error',
                            'message' => 'Shipping creation failed: ' . $e->getMessage()
                        ];

                        // Update transaction with error details
                        $transaction->update([
                            'shipping_status' => 'error',
                            'shipping_details' => json_encode([
                                'error' => $e->getMessage(),
                                'shipping_provider' => 'shiprocket',
                                'created_at' => now()
                            ])
                        ]);
                    }

                    DB::commit();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Payment verified successfully',
                        'data' => [
                            'shipping_status' => $shippingResult['status'] ?? 'error',
                            'shipping_error' => ($shippingResult['status'] ?? '') === 'error' ?
                                    ($shippingResult['message'] ?? 'Shipping creation failed') : null
                        ]
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to update payment status: ' . $e->getMessage());
                    throw $e;
                }
            }

            throw new \Exception('Payment signature verification failed');
        } catch (\Exception $e) {
            if (isset($transaction)) {
                DB::rollBack();
            }
            Log::error('Payment verification failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRazorpayOrderItems($transactionId)
    {
        try {
            $paymentTransaction = PaymentTransaction::where('transaction_id', $transactionId)
                ->firstOrFail();

            // Get items from metadata
            $metadata = json_decode($paymentTransaction->metadata, true);
            $items = $metadata['items'] ?? [];

            // Format items with size, color, and SKU code
            $formattedItems = array_map(function($item) {
                // Get product details to fetch SKU code
                $product = null;
                if (isset($item['product_id'])) {
                    $product = \App\Models\Product::find($item['product_id']);
                }

                return [
                    'product_id' => $item['product_id'] ?? null,
                    'size' => $item['size'] ?? null,
                    'color' => $item['color'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'price' => $item['price'] ?? 0,
                    'name' => $item['name'] ?? $item['product_name'] ?? '',
                    'tax_percentage' => $item['tax_percentage'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'sku_code' => $product ? $product->sku_code : null
                ];
            }, $items);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'items' => $formattedItems
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Razorpay order items: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch order items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process Razorpay webhook
     */
    public function webhook(Request $request)
    {
        try {
            // Get the raw payload and signature
            $payload = $request->all();
            $signature = $request->header('X-Razorpay-Signature');
            $rawBody = $request->getContent(); // Get raw body for signature verification

            Log::info('Webhook received', [
                'event' => $payload['event'] ?? 'unknown',
                'has_signature' => !empty($signature),
                'body_length' => strlen($rawBody),
                'content_type' => $request->header('Content-Type')
            ]);

            if (!$signature) {
                Log::error('Razorpay webhook signature missing');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Signature missing'
                ], 400);
            }

            // Store raw body temporarily for signature verification
            $GLOBALS['webhook_raw_body'] = $rawBody;

            $result = $this->paymentService->processWebhook($payload, $signature);

            // Clean up
            unset($GLOBALS['webhook_raw_body']);

            if ($result['status'] === 'success') {
                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }

        } catch (\Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment directly from Razorpay API
     * This endpoint can be used to resolve pending payment issues
     */
    public function verifyPaymentDirect(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_id' => 'required|string',
                'order_id' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->paymentService->verifyAndUpdatePayment(
                $request->payment_id,
                $request->order_id
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Direct payment verification failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending payments that need verification
     */
    public function getPendingPayments()
    {
        try {
            $pendingPayments = $this->paymentService->getPendingPayments();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'pending_payments' => $pendingPayments->map(function($payment) {
                        return [
                            'id' => $payment->id,
                            'razorpay_order_id' => $payment->razorpay_order_id,
                            'razorpay_payment_id' => $payment->razorpay_payment_id,
                            'amount' => $payment->amount,
                            'status' => $payment->status,
                            'created_at' => $payment->created_at,
                            'transaction' => $payment->transaction ? [
                                'id' => $payment->transaction->id,
                                'order_number' => $payment->transaction->order_number,
                                'customer_name' => $payment->transaction->customer_name,
                                'payment_status' => $payment->transaction->payment_status
                            ] : null,
                            'user' => $payment->user ? [
                                'id' => $payment->user->id,
                                'name' => $payment->user->name,
                                'email' => $payment->user->email
                            ] : null
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get pending payments: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get pending payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk verify all pending payments
     */
    public function verifyPendingPayments()
    {
        try {
            $results = $this->paymentService->verifyPendingPayments();

            $successCount = 0;
            $errorCount = 0;

            foreach ($results as $result) {
                if ($result['result']['status'] === 'success') {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Bulk verification completed. Success: {$successCount}, Errors: {$errorCount}",
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total' => count($results),
                        'success' => $successCount,
                        'errors' => $errorCount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk payment verification failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug webhook signature verification
     * This endpoint helps debug webhook signature issues
     */
    public function debugWebhook(Request $request)
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Razorpay-Signature');
            $rawBody = $request->getContent();

            // Get webhook secret
            $webhookSecret = \App\Models\PaymentGatewaySetting::where('gateway_name', 'razorpay')
                ->where('is_active', true)
                ->value('webhook_secret');

            $debugInfo = [
                'timestamp' => now()->toISOString(),
                'has_signature' => !empty($signature),
                'signature_length' => strlen($signature ?? ''),
                'has_webhook_secret' => !empty($webhookSecret),
                'webhook_secret_length' => strlen($webhookSecret ?? ''),
                'raw_body_length' => strlen($rawBody),
                'payload_keys' => array_keys($payload),
                'event' => $payload['event'] ?? 'unknown',
                'content_type' => $request->header('Content-Type'),
                'user_agent' => $request->header('User-Agent'),
            ];

            // Try signature verification if both signature and secret are present
            if (!empty($signature) && !empty($webhookSecret)) {
                try {
                    $razorpay = new \Razorpay\Api\Api(
                        \App\Models\PaymentGatewaySetting::where('gateway_name', 'razorpay')
                            ->where('is_active', true)
                            ->value('key_id'),
                        \App\Models\PaymentGatewaySetting::where('gateway_name', 'razorpay')
                            ->where('is_active', true)
                            ->value('key_secret')
                    );

                    $razorpay->utility->verifyWebhookSignature($rawBody, $signature, $webhookSecret);
                    $debugInfo['signature_verification'] = 'SUCCESS';
                } catch (\Exception $e) {
                    $debugInfo['signature_verification'] = 'FAILED';
                    $debugInfo['signature_error'] = $e->getMessage();

                    // Try manual HMAC verification
                    $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);
                    $debugInfo['manual_hmac_match'] = hash_equals($expectedSignature, $signature);
                    $debugInfo['expected_signature_preview'] = substr($expectedSignature, 0, 20) . '...';
                    $debugInfo['received_signature_preview'] = substr($signature, 0, 20) . '...';
                }
            }

            Log::info('Webhook debug info', $debugInfo);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook debug information collected',
                'debug_info' => $debugInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook debug failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook debug failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateUniqueOrderNumber()
    {
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $date = now()->format('Ymd');
            $micro = str_pad(substr(now()->format('u'), 0, 3), 3, '0');
            $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            $orderNumber = "ORD-{$date}-{$micro}{$random}";

            // Check if this order number exists
            if (!Order::where('order_number', $orderNumber)->exists()) {
                return $orderNumber;
            }

            $attempt++;
            // Add a small delay to avoid collisions in high-concurrency situations
            usleep(10000); // 10ms delay
        }

        throw new \Exception('Unable to generate unique order number after ' . $maxAttempts . ' attempts');
    }

    private function updateProductStock($productId, $quantity, $selectedSize = null)
    {
        try {
            $product = \App\Models\Product::findOrFail($productId);

            if (!$product) {
                Log::error("Stock update failed: Product #$productId not found");
                return;
            }

            $sizes = is_array($product->sizes) ? $product->sizes : json_decode($product->sizes, true);

            if (empty($sizes)) {
                Log::error("Stock update failed: Product #$productId has no sizes configuration");
                return;
            }

            if ($selectedSize) {
                // Update stock for specific size
                foreach ($sizes as &$size) {
                    if ($size['size'] === $selectedSize) {
                        $currentStock = (int)($size['stock'] ?? 0);
                        if ($currentStock < $quantity) {
                            throw new \Exception("Insufficient stock for Product #$productId size $selectedSize");
                        }
                        $size['stock'] = $currentStock - $quantity;
                        break;
                    }
                }
            } else {
                // If no size specified, reduce from the first available size with enough stock
                $quantityToReduce = $quantity;
                foreach ($sizes as &$size) {
                    $currentStock = (int)($size['stock'] ?? 0);
                    if ($currentStock > 0) {
                        $reduceAmount = min($currentStock, $quantityToReduce);
                        $size['stock'] = $currentStock - $reduceAmount;
                        $quantityToReduce -= $reduceAmount;

                        if ($quantityToReduce <= 0) break;
                    }
                }

                if ($quantityToReduce > 0) {
                    throw new \Exception("Insufficient total stock for Product #$productId");
                }
            }

            $product->sizes = json_encode($sizes);
            $product->save();

            Log::info("Stock updated successfully for Product #$productId", [
                'quantity_reduced' => $quantity,
                'size' => $selectedSize ?? 'any',
                'updated_sizes' => $sizes
            ]);

        } catch (\Exception $e) {
            Log::error("Error updating stock for Product #$productId: " . $e->getMessage());
            throw $e;
        }
    }
}
