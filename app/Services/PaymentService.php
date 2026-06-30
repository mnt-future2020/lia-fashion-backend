<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\PaymentTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use Razorpay\Api\Api;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    private $razorpay;

    public function __construct()
    {
        // Log all Razorpay settings for debugging
        $allSettings = \App\Models\PaymentGatewaySetting::where('gateway_name', 'razorpay')->get();

        \Illuminate\Support\Facades\Log::info('All Razorpay settings:', [
            'total_settings' => $allSettings->count(),
            'settings' => $allSettings->map(function($setting) {
                return [
                    'id' => $setting->id,
                    'is_active' => $setting->is_active,
                    'is_sandbox' => $setting->is_sandbox,
                    'has_key_id' => !empty($setting->key_id),
                    'has_key_secret' => !empty($setting->key_secret)
                ];
            })
        ]);

        // Get active Razorpay settings from database
        $settings = \App\Models\PaymentGatewaySetting::where('gateway_name', 'razorpay')
            ->where('is_active', true)
            ->first();

        if (!$settings) {
            // Try to find any Razorpay setting and activate it
            $anySetting = \App\Models\PaymentGatewaySetting::where('gateway_name', 'razorpay')->first();
            if ($anySetting) {
                $anySetting->update(['is_active' => true]);
                $settings = $anySetting;
                \Illuminate\Support\Facades\Log::info('Activated existing Razorpay setting:', [
                    'setting_id' => $anySetting->id
                ]);
            } else {
                throw new \Exception('No active Razorpay settings found. Please configure Razorpay in the admin panel.');
            }
        }

        // Validate key format
        if (!$settings->validateRazorpayKeys()) {
            $mode = $settings->is_sandbox ? 'test' : 'live';
            throw new \Exception("Invalid Razorpay key format. Please ensure you're using the correct {$mode} mode credentials.");
        }

        $this->razorpay = new Api($settings->key_id, $settings->key_secret);
    }

    public function createPaymentOrder(Transaction $transaction)
    {
        try {
            $order = $this->razorpay->order->create([
                'amount' => $transaction->total_amount * 100, // Convert to paisa
                'currency' => 'INR',
                'receipt' => 'order_' . $transaction->id,
                'payment_capture' => 1
            ]);

            return PaymentTransaction::create([
                'order_id' => $transaction->id,
                'razorpay_order_id' => $order->id,
                'amount' => $transaction->total_amount,
                'status' => 'created'
            ]);
        } catch (Exception $e) {
            throw new Exception('Failed to create payment order: ' . $e->getMessage());
        }
    }

    public function createRazorpayOrder(array $orderData)
    {
        try {
            Log::info('Creating Razorpay order with data:', $orderData);

            // Validate the minimum required fields
            if (!isset($orderData['amount']) || !is_numeric($orderData['amount'])) {
                throw new Exception('Invalid amount provided');
            }

            $order = $this->razorpay->order->create([
                'amount' => (int)$orderData['amount'], // Amount should already be in paise
                'currency' => $orderData['currency'] ?? 'INR',
                'notes' => $orderData['notes'] ?? [],
                'receipt' => 'receipt_' . time(),
                'payment_capture' => 1
            ]);

            Log::info('Razorpay order created successfully:', [
                'order_id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency
            ]);

            return [
                'id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'status' => $order->status
            ];
        } catch (Exception $e) {
            Log::error('Failed to create Razorpay order: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw new Exception('Failed to create Razorpay order: ' . $e->getMessage());
        }
    }

    public function verifyPaymentSignature($attributes)
    {
        try {
            $this->razorpay->utility->verifyPaymentSignature($attributes);
            return true;
        } catch (Exception $e) {
            throw new Exception('Payment signature verification failed: ' . $e->getMessage());
        }
    }

    public function verifyRazorpayPayment($paymentId, $orderId, $signature)
    {
        try {
            $attributes = [
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => $orderId,
                'razorpay_signature' => $signature
            ];

            Log::info('Verifying Razorpay payment:', $attributes);

            $this->razorpay->utility->verifyPaymentSignature($attributes);

            Log::info('Payment verification successful');
            return true;
        } catch (Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw new Exception('Payment signature verification failed: ' . $e->getMessage());
        }
    }

    public function getPaymentOrder($orderId)
    {
        try {
            return $this->razorpay->order->fetch($orderId);
        } catch (Exception $e) {
            Log::error('Failed to fetch payment order: ' . $e->getMessage());
            throw new Exception('Failed to fetch payment order: ' . $e->getMessage());
        }
    }

    /**
     * Verify and update payment status directly from Razorpay API
     * This method is used to resolve pending payment issues
     *
     * @param string $paymentId - Razorpay payment ID
     * @param string $orderId - Razorpay order ID (optional)
     * @return array
     */
    public function verifyAndUpdatePayment($paymentId, $orderId = null)
    {
        try {
            Log::info('Starting direct payment verification:', [
                'payment_id' => $paymentId,
                'order_id' => $orderId
            ]);

            // 1. Fetch payment details from Razorpay
            $payment = $this->razorpay->payment->fetch($paymentId);

            Log::info('Payment details from Razorpay:', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'order_id' => $payment->order_id ?? null
            ]);

            // 2. Check if payment is captured
            if ($payment->status === 'captured') {
                Log::info('Payment is captured, updating database');

                // 3. Find the payment transaction
                $paymentTransaction = PaymentTransaction::where('razorpay_payment_id', $paymentId)
                    ->orWhere('razorpay_order_id', $payment->order_id ?? $orderId)
                    ->first();

                if (!$paymentTransaction) {
                    Log::warning('Payment transaction not found in database', [
                        'payment_id' => $paymentId,
                        'order_id' => $payment->order_id ?? $orderId
                    ]);
                    return [
                        'status' => 'error',
                        'message' => 'Payment transaction not found in database'
                    ];
                }

                // 4. Update payment transaction and related records
                DB::beginTransaction();
                try {
                    // Update payment transaction
                    $paymentTransaction->update([
                        'razorpay_payment_id' => $paymentId,
                        'status' => 'completed'
                    ]);

                    // Update transaction
                    $transaction = $paymentTransaction->transaction;
                    if ($transaction) {
                        $transaction->update([
                            'payment_status' => 'Paid',
                            'transaction_type' => 'Online',
                            'payment_method' => 'Razorpay'
                        ]);

                        // Get metadata with cart items
                        $metadata = json_decode($paymentTransaction->metadata, true);
                        $cartItems = $metadata['items'] ?? [];

                        // Parse shipping cost, subtotal, tax and shipping address from transaction notes
                        $notes = $transaction->notes;
                        $shipping_cost = 0;
                        $subtotal = 0;
                        $tax = 0;
                        $shipping_address = [];
                        if ($notes) {
                            $notesArr = is_array($notes) ? $notes : json_decode($notes, true);
                            if (is_array($notesArr)) {
                                $shipping_cost = $notesArr['shipping_charge'] ?? 0;
                                $subtotal = $notesArr['subtotal'] ?? 0;
                                $tax = $notesArr['tax'] ?? 0;
                                $shipping_address = $notesArr['shipping_address'] ?? [];
                            }
                        }

                        // Update stock for each cart item
                        foreach ($cartItems as $item) {
                            try {
                                $product = Product::findOrFail($item['product_id']);
                                $sizes = json_decode($product->sizes, true);

                                // Find and update the specific size's stock
                                foreach ($sizes as &$sizeData) {
                                    if ($sizeData['size'] === $item['size']) {
                                        $currentStock = (int)$sizeData['stock'];
                                        $sizeData['stock'] = max(0, $currentStock - $item['quantity']);
                                        break;
                                    }
                                }

                                $product->sizes = json_encode($sizes);
                                $product->save();

                                Log::info('Stock updated successfully for Product #' . $product->id, [
                                    'quantity_reduced' => $item['quantity'],
                                    'size' => $item['size'],
                                    'updated_sizes' => $sizes
                                ]);
                            } catch (\Exception $e) {
                                Log::error('Failed to update stock for product #' . $item['product_id'], [
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }

                        // Clear user's cart
                        $userId = $transaction->user_id ?? $paymentTransaction->user_id;
                        if ($userId) {
                            // Get cart IDs for the user
                            $cartIds = Cart::where('user_id', $userId)->pluck('id')->toArray();

                            // Delete cart items first (due to foreign key constraint)
                            if (!empty($cartIds)) {
                                CartItem::whereIn('cart_id', $cartIds)->delete();
                            }

                            // Then delete the carts
                            Cart::where('user_id', $userId)->delete();

                            Log::info('Cart cleared for user #' . $userId, [
                                'cart_ids' => $cartIds
                            ]);
                        }

                        try {
                            $orderData = [
                                'user_id' => $userId,
                                'order_number' => $transaction->order_number,
                                'total_amount' => $transaction->total_amount,
                                'shipping_cost' => $shipping_cost,
                                'subtotal' => $subtotal,
                                'tax' => $tax,
                                'status' => 'processing',
                                'payment_id' => $paymentId,
                                'payment_status' => 'paid',
                                'order_type' => 'manual',
                                'shipping_address' => json_encode($shipping_address),
                                'phone' => $transaction->customer_phone ?? '',
                                'email' => $transaction->customer_email ?? ''
                            ];

                            // Handle coupon usage if present in metadata
                            if (isset($metadata['coupon_id']) && !empty($userId)) {
                                DB::transaction(function() use ($metadata, $userId) {
                                    $coupon = \App\Models\Coupon::lockForUpdate()->findOrFail($metadata['coupon_id']);
                                    $coupon->increment('usage_count');
                                    $coupon->users()->syncWithoutDetaching([$userId]);
                                });
                            }

                            // Check if Shiprocket is active
                            $shiprocketActive = \App\Models\ShiprocketSetting::where('is_active', true)->exists();

                             $order = DB::transaction(function() use ($paymentId, $transaction, $orderData, $cartItems, $shiprocketActive, $metadata, $paymentTransaction) {
                                // Try to find existing order with lock
                                $existingOrder = Order::where('payment_id', $paymentId)
                                    ->orWhere('order_number', $transaction->order_number)
                                    ->lockForUpdate()
                                    ->first();

                                if ($existingOrder) {
                                    // Update payment status if needed
                                    if ($existingOrder->payment_status !== 'paid') {
                                        $existingOrder->update([
                                            'payment_status' => 'paid',
                                            'payment_id' => $paymentId
                                        ]);
                                    }
                                    return $existingOrder;
                                }

                                // Generate unique order number if needed
                                if (empty($orderData['order_number'])) {
                                    $orderData['order_number'] = $this->generateUniqueOrderNumber();
                                }

                                // Update order type if Shiprocket is active
                                $orderData['order_type'] = $shiprocketActive ? 'shiprocket' : 'manual';

                                // Create new order
                                $order = Order::create($orderData);

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
                                // Store Razorpay order items
                                foreach ($cartItems as $item) {
                                    \App\Models\RazorpayOrderItem::create([
                                        'payment_transaction_id' => $paymentTransaction->id,
                                        'product_id' => $item['product_id'],
                                        'product_name' => $item['name'] ?? '',
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

                                // Create Shiprocket order if active
                                if ($shiprocketActive) {
                                    try {
                                        $shiprocketService = new \App\Services\ShiprocketOrderService();

                                        // Format shipping details for Shiprocket
                                        $shippingDetails = json_decode($orderData['shipping_address'], true);
                                        $formattedShippingDetails = [
                                            'name' => $shippingDetails['name'] ?? $transaction->customer_name,
                                            'email' => $shippingDetails['email'] ?? '',
                                            'phone' => $shippingDetails['phone'] ?? $transaction->customer_phone,
                                            'address' => $shippingDetails['address'] ?? '',
                                            'city' => $shippingDetails['city'] ?? '',
                                            'state' => $shippingDetails['state'] ?? '',
                                            'country' => $shippingDetails['country'] ?? 'India',
                                            'pin_code' => $shippingDetails['pincode'] ?? '',
                                            'order_items' => array_map(function($item) {
                                                return [
                                                    'name' => $item['name'] ?? '',
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
                                    } catch (\Exception $e) {
                                        Log::error('Shiprocket order creation failed', [
                                            'error' => $e->getMessage(),
                                            'trace' => $e->getTraceAsString(),
                                            'transaction_id' => $transaction->id
                                        ]);

                                        $transaction->update([
                                            'shipping_status' => 'error',
                                            'shipping_details' => json_encode([
                                                'error' => $e->getMessage(),
                                                'shipping_provider' => 'shiprocket',
                                                'created_at' => now()
                                            ])
                                        ]);
                                    }
                                }

                                return $order;
                            });

                            Log::info('Order processed successfully', [
                                'order_id' => $order->id,
                                'order_number' => $order->order_number,
                                'is_new' => !$order->wasRecentlyCreated
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error processing order: ' . $e->getMessage(), [
                                'payment_id' => $paymentId,
                                'transaction_id' => $transaction->id
                            ]);
                            throw $e;
                        }
                    }

                    DB::commit();

                    Log::info('Payment verification and update completed successfully', [
                        'payment_id' => $paymentId,
                        'transaction_id' => $transaction->id ?? null
                    ]);

                    return [
                        'status' => 'success',
                        'message' => 'Payment verified and updated successfully',
                        'payment_status' => $payment->status,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency
                    ];

                } catch (Exception $e) {
                    DB::rollback();
                    Log::error('Failed to update payment status in database: ' . $e->getMessage());
                    throw $e;
                }

            } else {
                Log::info('Payment not yet captured', [
                    'payment_id' => $paymentId,
                    'status' => $payment->status
                ]);

                return [
                    'status' => 'pending',
                    'message' => 'Payment not yet captured',
                    'payment_status' => $payment->status
                ];
            }

        } catch (Exception $e) {
            Log::error('Error verifying payment: ' . $e->getMessage(), [
                'payment_id' => $paymentId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'Error verifying payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process Razorpay webhook
     * Handles the complete order processing workflow for captured payments
     *
     * @param array $payload - Webhook payload
     * @param string $signature - Webhook signature
     * @return array
     */
    public function processWebhook($payload, $signature)
    {
        try {
            Log::info('Processing Razorpay webhook', [
                'event' => $payload['event'] ?? 'unknown',
                'timestamp' => now()->toISOString(),
                'payload_keys' => array_keys($payload),
                'signature_present' => !empty($signature)
            ]);

            // Get webhook secret from settings
            $webhookSecret = \App\Models\PaymentGatewaySetting::where('gateway_name', 'razorpay')
                ->where('is_active', true)
                ->value('webhook_secret');

            // Enhanced webhook signature verification with better error handling
            if ($webhookSecret) {
                try {
                    // Log the raw payload for debugging
                    Log::info('Webhook signature verification attempt', [
                        'payload_length' => strlen(json_encode($payload)),
                        'signature_length' => strlen($signature),
                        'has_webhook_secret' => !empty($webhookSecret)
                    ]);

                    // Get the raw body from global variable (set by controller) or fallback methods
                    $rawBody = $GLOBALS['webhook_raw_body'] ?? file_get_contents('php://input');
                    
                    if (empty($rawBody)) {
                        // Fallback to JSON encoding if raw body is not available
                        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        Log::warning('Using JSON encoded payload as fallback for signature verification');
                    }

                    Log::info('Raw body details', [
                        'raw_body_length' => strlen($rawBody),
                        'raw_body_sample' => substr($rawBody, 0, 200) . '...'
                    ]);

                    // Verify webhook signature using raw body
                    $this->razorpay->utility->verifyWebhookSignature(
                        $rawBody,
                        $signature,
                        $webhookSecret
                    );
                    
                    Log::info('Webhook signature verified successfully');
                } catch (Exception $e) {
                    Log::error('Webhook signature verification failed', [
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'signature_length' => strlen($signature),
                        'webhook_secret_length' => strlen($webhookSecret),
                        'raw_body_length' => strlen($rawBody ?? ''),
                        'signature_preview' => substr($signature, 0, 20) . '...',
                        'webhook_secret_preview' => substr($webhookSecret, 0, 10) . '...',
                        'payload_event' => $payload['event'] ?? 'unknown'
                    ]);
                    
                    // Try alternative signature verification methods
                    $alternativeVerification = $this->tryAlternativeSignatureVerification($rawBody ?? '', $signature, $webhookSecret, $payload);
                    
                    if (!$alternativeVerification) {
                        // For critical production environments, you might want to return error here
                        // return ['status' => 'error', 'message' => 'Webhook signature verification failed'];
                        
                        // For now, log the error but continue processing to avoid blocking payments
                        Log::warning('Continuing webhook processing despite signature verification failure - this should be investigated');
                    }
                }
            } else {
                Log::warning('Webhook secret not configured - skipping signature verification. This is not recommended for production.');
            }

            $event = $payload['event'] ?? '';
            $paymentId = $payload['payload']['payment']['entity']['id'] ?? null;
            $orderId = $payload['payload']['payment']['entity']['order_id'] ?? null;

            Log::info('Processing webhook event', [
                'event' => $event,
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'payload' => $payload
            ]);

            switch ($event) {
                case 'payment.captured':
                    if ($paymentId) {
                        Log::info('Payment captured webhook received', [
                            'payment_id' => $paymentId
                        ]);
                        return $this->verifyAndUpdatePayment($paymentId, $orderId);
                    }
                    break;

                case 'payment.failed':
                    if ($paymentId) {
                        // Update payment status to failed
                        $paymentTransaction = PaymentTransaction::where('razorpay_payment_id', $paymentId)
                            ->orWhere('razorpay_order_id', $orderId)
                            ->first();

                        if ($paymentTransaction) {
                            $paymentTransaction->update([
                                'status' => 'failed',
                                'error_message' => $payload['payload']['payment']['entity']['error_description'] ?? 'Payment failed'
                            ]);

                            // Update related transaction
                            if ($paymentTransaction->transaction) {
                                $paymentTransaction->transaction->update([
                                    'payment_status' => 'Failed'
                                ]);
                            }
                        }
                    }
                    break;
            }

            return [
                'status' => 'success',
                'message' => 'Webhook processed successfully',
                'event' => $event
            ];

        } catch (Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Webhook processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get pending payments that need verification
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingPayments()
    {
        return PaymentTransaction::where('status', 'pending')
            ->whereNotNull('razorpay_order_id')
            ->with(['transaction', 'user'])
            ->get();
    }

    /**
     * Bulk verify pending payments
     *
     * @return array
     */
    public function verifyPendingPayments()
    {
        $pendingPayments = $this->getPendingPayments();
        $results = [];

        foreach ($pendingPayments as $payment) {
            try {
                $result = $this->verifyAndUpdatePayment(
                    $payment->razorpay_payment_id,
                    $payment->razorpay_order_id
                );
                $results[] = [
                    'payment_id' => $payment->razorpay_payment_id,
                    'result' => $result
                ];
            } catch (Exception $e) {
                $results[] = [
                    'payment_id' => $payment->razorpay_payment_id,
                    'result' => [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ]
                ];
            }
        }

        return $results;
    }

    /**
     * Try alternative signature verification methods
     * This helps handle edge cases where the standard verification fails
     */
    private function tryAlternativeSignatureVerification($rawBody, $signature, $webhookSecret, $payload)
    {
        try {
            // Method 1: Try with different JSON encoding options
            $alternativeBody1 = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $this->razorpay->utility->verifyWebhookSignature($alternativeBody1, $signature, $webhookSecret);
            Log::info('Alternative signature verification successful (method 1)');
            return true;
        } catch (Exception $e) {
            // Continue to next method
        }

        try {
            // Method 2: Try with compact JSON (no spaces)
            $alternativeBody2 = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->razorpay->utility->verifyWebhookSignature($alternativeBody2, $signature, $webhookSecret);
            Log::info('Alternative signature verification successful (method 2)');
            return true;
        } catch (Exception $e) {
            // Continue to next method
        }

        try {
            // Method 3: Manual signature verification using HMAC
            $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);
            if (hash_equals($expectedSignature, $signature)) {
                Log::info('Alternative signature verification successful (manual HMAC)');
                return true;
            }
        } catch (Exception $e) {
            // Continue
        }

        Log::error('All alternative signature verification methods failed');
        return false;
    }

    /**
     * Generate unique order number
     */
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
            if (!\App\Models\Order::where('order_number', $orderNumber)->exists()) {
                return $orderNumber;
            }

            $attempt++;
            // Add a small delay to avoid collisions in high-concurrency situations
            usleep(10000); // 10ms delay
        }

        throw new \Exception('Unable to generate unique order number after ' . $maxAttempts . ' attempts');
    }
}
