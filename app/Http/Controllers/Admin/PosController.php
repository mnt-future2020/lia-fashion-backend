<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosCustomer;
use App\Models\PosOrderItem;
use App\Models\Product;
use App\Models\InvoiceSetting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PosController extends Controller
{
    /**
     * Get the next available order number.
     *
     * @return \Illuminate\Http\Response
     */
    public function getNextOrderNumber()
    {
        try {
            // Find the latest order number with the LIA_ prefix
            $latestOrder = PosCustomer::where('order_number', 'LIKE', 'LIA_%')
                ->orderBy('id', 'desc')
                ->first();

            if ($latestOrder) {
                // Extract the numeric part and increment
                $numericPart = intval(substr($latestOrder->order_number, 4));
                $nextNumber = $numericPart + 1;
            } else {
                // Start with 1 if no previous orders
                $nextNumber = 1;
            }

            // Format with leading zeros (e.g., LIA_001)
            $nextOrderNumber = 'LIA_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Add invoice number information for fallback
            $invoiceNumber = null;
            $setting = \App\Models\InvoiceSetting::where('is_active', true)->first();

            if ($setting) {
                // Create an invoice number using the setting
                $newSequence = $setting->last_sequence_number + 1;
                $fyStartYear = \Carbon\Carbon::parse($setting->financial_year_start)->format('y');
                $fyEndYear = \Carbon\Carbon::parse($setting->financial_year_end)->format('y');
                $invoiceNumber = $setting->prefix . $fyStartYear . $fyEndYear . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
            }

            return response()->json([
                'next_order_number' => $nextOrderNumber,
                'invoice_info' => [
                    'invoice_number' => $invoiceNumber,
                    'prefix' => $setting ? $setting->prefix : 'LIA',
                    'sequence' => $setting ? $setting->last_sequence_number + 1 : 1
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting next order number: ' . $e->getMessage());
            return response()->json([
                'next_order_number' => 'LIA_' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store a customer order from POS.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'invoice_number' => 'nullable|string',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'payment_method' => 'required|string|in:Cash,Card,UPI/QR',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.size' => 'nullable|string|max:50',
            'items.*.color' => 'nullable|string|max:50',
            'items.*.tax_percentage' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get invoice number from request or generate a new one
            $invoiceNumber = $request->invoice_number;

            \Log::info('POS Order - Received invoice number: ' . $invoiceNumber);

            // Check if invoice number format is correct (e.g., LIA2425####)
            // A valid format should be prefix (3 chars) + 2 years (4 chars) + sequence (4 chars)
            if (!$invoiceNumber || strlen($invoiceNumber) < 11) {
                // If no invoice number or invalid format, get one from InvoiceSettingController
                \Log::info('POS Order - No valid invoice number, generating a new one');

                try {
                    // Use the InvoiceSettingController to generate a new invoice number
                    $invoiceController = new \App\Http\Controllers\InvoiceSettingController();
                    $invoiceResponse = $invoiceController->generateInvoiceNumber();

                    // Get the response content
                    $responseData = json_decode($invoiceResponse->getContent(), true);

                    if (isset($responseData['invoice_number'])) {
                        $invoiceNumber = $responseData['invoice_number'];
                        \Log::info('POS Order - Generated new invoice number: ' . $invoiceNumber);

                        // Fallback if generation fails
                        $setting = InvoiceSetting::where('is_active', true)->first();
                        if ($setting) {
                            // Get next sequence manually
                            $newSequence = $setting->last_sequence_number + 1;
                            $fyStartYear = Carbon::parse($setting->financial_year_start)->format('y');
                            $fyEndYear = Carbon::parse($setting->financial_year_end)->format('y');
                            $invoiceNumber = $setting->prefix . $fyStartYear . $fyEndYear . str_pad($newSequence, 4, '0', STR_PAD_LEFT);

                            // Update the invoice settings
                            $setting->update([
                                'last_invoice_number' => $invoiceNumber,
                                'last_sequence_number' => $newSequence
                            ]);

                            \Log::info('POS Order - Generated fallback invoice number: ' . $invoiceNumber);
                        } else {
                            // Last resort fallback if no settings exist
                            $invoiceNumber = 'INV-' . date('Ymd') . '-' . random_int(1000, 9999);
                            \Log::warning('POS Order - Used emergency fallback invoice number: ' . $invoiceNumber);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('POS Order - Failed to generate invoice number: ' . $e->getMessage());
                    $invoiceNumber = 'INV-' . date('Ymd') . '-' . random_int(1000, 9999);
                }
            } else {
                \Log::info('POS Order - Using provided invoice number: ' . $invoiceNumber);

                // Don't update the sequence number in InvoiceSettings here - it's already been updated
                // when the frontend called the generate-number endpoint
            }

            // Create customer record or use existing
            $customer = null;
            if ($request->customer_name || $request->customer_phone || $request->customer_email) {
                $customer = PosCustomer::updateOrCreate(
                    [
                        'phone' => $request->customer_phone
                    ],
                    [
                        'name' => $request->customer_name,
                        'email' => $request->customer_email,
                        'order_number' => $request->order_number,
                        'payment_method' => $request->payment_method,
                        'total_amount' => $request->total_amount,
                        'transaction_date' => Carbon::now(),
                        'payment_status' => 'Paid',
                        'transaction_type' => 'POS',
                        'invoice_number' => $invoiceNumber,
                        'order_date' => Carbon::now(),
                    ]
                );
            } else {
                // Create a record for walk-in customer
                $customer = PosCustomer::create([
                    'name' => 'Walk-in Customer',
                    'order_number' => $request->order_number,
                    'payment_method' => $request->payment_method,
                    'total_amount' => $request->total_amount,
                    'transaction_date' => Carbon::now(),
                    'payment_status' => 'Paid',
                    'transaction_type' => 'POS',
                    'invoice_number' => $invoiceNumber,
                    'order_date' => Carbon::now(),
                ]);
            }

            // Create transaction
            $transaction = Transaction::create([
                'pos_customer_id' => $customer->id,
                'order_number' => $request->order_number,
                'invoice_number' => $invoiceNumber,
                'payment_method' => $request->payment_method,
                'total_amount' => $request->total_amount,
                'subtotal_amount' => $request->total_amount - array_sum(array_map(function($item) {
                    return ($item['price'] * $item['quantity'] * $item['tax_percentage'] / 100);
                }, $request->items)),
                'tax_amount' => array_sum(array_map(function($item) {
                    return ($item['price'] * $item['quantity'] * $item['tax_percentage'] / 100);
                }, $request->items)),
                'transaction_date' => Carbon::now(),
                'payment_status' => 'Paid',
                'transaction_type' => 'POS',
                'customer_name' => $request->customer_name ?? 'Walk-in Customer',
                'customer_phone' => $request->customer_phone,
                'customer_email' => $request->customer_email,
            ]);

            // Create order items
            foreach ($request->items as $item) {
                PosOrderItem::create([
                    'transaction_id' => $transaction->id,
                    'pos_customer_id' => $customer->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'size' => $item['size'] ?? null,
                    'color' => $item['color'] ?? null,
                    'tax_percentage' => $item['tax_percentage'] ?? 0,
                    'tax_amount' => $item['price'] * $item['quantity'] * ($item['tax_percentage'] / 100),
                    'subtotal' => $item['price'] * $item['quantity'],
                ]);

                // Update product stock with size information
                $this->updateProductStock($item['product_id'], $item['quantity'], $item['size'] ?? null);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $transaction->id,
                    'order_number' => $transaction->order_number,
                    'invoice_number' => $transaction->invoice_number,
                    'total_amount' => $transaction->total_amount,
                    'transaction_date' => $transaction->transaction_date,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('POS Order - Failed to create order: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all POS orders with pagination.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getOrders(Request $request)
    {
        $orders = PosCustomer::with('orderItems')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Get a specific order with its items.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getOrder($id)
    {
        $order = PosCustomer::with('orderItems')->findOrFail($id);
        return response()->json($order);
    }

    /**
     * Helper method to get the next order number value.
     *
     * @return string
     */
    private function getNextOrderNumberValue()
    {
        // Find the latest order number with the LIA_ prefix
        $latestOrder = PosCustomer::where('order_number', 'LIKE', 'LIA_%')
            ->orderBy('id', 'desc')
            ->first();

        if ($latestOrder) {
            // Extract the numeric part and increment
            $numericPart = intval(substr($latestOrder->order_number, 4));
            $nextNumber = $numericPart + 1;
        } else {
            // Start with 1 if no previous orders
            $nextNumber = 1;
        }

        // Format with leading zeros (e.g., LIA_001)
        return 'LIA_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Process a checkout/sale.
     */
    public function checkout(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validate request
            $validator = Validator::make($request->all(), [
                'customer_name' => 'nullable|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'customer_email' => 'nullable|email|max:255',
                'payment_method' => 'required|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.size' => 'nullable|string|max:50',
                'items.*.color' => 'nullable|string|max:50',
                'invoice_number' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate order number
            $orderNumber = 'POS-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

            // Create customer record
            $customer = PosCustomer::create([
                'name' => $request->customer_name ?? 'Walk-in Customer',
                'phone' => $request->customer_phone,
                'email' => $request->customer_email,
                'amount' => $request->total_amount,
                'order_number' => $orderNumber,
                'invoice_number' => $request->invoice_number,
                'payment_method' => $request->payment_method,
                'order_date' => now(),
            ]);

            // Calculate totals first
            $subtotal = 0;
            $taxTotal = 0;

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $taxPercentage = $product->tax_percentage ?? 0;
                $itemPrice = $item['price'];
                $itemQuantity = $item['quantity'];

                $itemSubtotal = $itemPrice * $itemQuantity;
                $itemTax = ($itemSubtotal * $taxPercentage) / 100;

                $subtotal += $itemSubtotal;
                $taxTotal += $itemTax;
            }

            $totalAmount = $subtotal + $taxTotal;

            // Create transaction record first
            $transaction = Transaction::create([
                'pos_customer_id' => $customer->id,
                'invoice_number' => $request->invoice_number,
                'order_number' => $orderNumber,
                'transaction_type' => 'POS',
                'payment_method' => $request->payment_method,
                'payment_status' => 'Paid',
                'subtotal_amount' => $subtotal,
                'tax_amount' => $taxTotal,
                'total_amount' => $totalAmount,
                'transaction_date' => now(),
                'customer_name' => $request->customer_name ?? 'Walk-in Customer',
                'customer_phone' => $request->customer_phone,
            ]);

            // Now create order items with the transaction ID
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $taxPercentage = $product->tax_percentage ?? 0;
                $itemPrice = $item['price'];
                $itemQuantity = $item['quantity'];

                $itemSubtotal = $itemPrice * $itemQuantity;
                $itemTax = ($itemSubtotal * $taxPercentage) / 100;

                // Create order item with transaction ID
                PosOrderItem::create([
                    'pos_customer_id' => $customer->id,
                    'transaction_id' => $transaction->id,  // Now we have a valid transaction ID
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name,
                    'quantity' => $itemQuantity,
                    'price' => $itemPrice,
                    'size' => $item['size'] ?? null,
                    'color' => $item['color'] ?? null,
                    'tax_percentage' => $taxPercentage,
                    'tax_amount' => $itemTax,
                    'subtotal' => $itemSubtotal + $itemTax,
                ]);

                // Update product stock with size information
                $this->updateProductStock($item['product_id'], $itemQuantity, $item['size'] ?? null);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Checkout completed successfully',
                'data' => [
                    'customer' => $customer,
                    'transaction' => $transaction,
                    'order_number' => $orderNumber,
                    'invoice_number' => $request->invoice_number,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product stock after an order is placed
     *
     * @param int $productId
     * @param int $quantity
     * @param string|null $size
     * @return void
     */
    private function updateProductStock($productId, $quantity, $size = null)
    {
        try {
            $product = \App\Models\Product::findOrFail($productId);

            if (!$product) {
                \Log::error("Stock update failed: Product #$productId not found");
                return;
            }

            // Check if $product->sizes is already an array or needs decoding
            if (is_string($product->sizes)) {
                $sizes = json_decode($product->sizes, true) ?: [];
            } else if (is_array($product->sizes)) {
                $sizes = $product->sizes;
            } else {
                $sizes = [];
                \Log::error("Stock update failed: Product #$productId has invalid sizes format: " . gettype($product->sizes));
            }

            if (empty($sizes)) {
                \Log::error("Stock update failed: Product #$productId has no sizes configuration");
                return;
            }

            $updated = false;

            if ($size) {
                // Reduce stock from the specific size that was ordered
                foreach ($sizes as &$sizeData) {
                    // Match the size (handle both array formats)
                    $sizeName = isset($sizeData['size']) ? $sizeData['size'] : (isset($sizeData['name']) ? $sizeData['name'] : '');

                    if (trim($sizeName) === trim($size)) {
                        $currentStock = isset($sizeData['stock']) ? (int)$sizeData['stock'] : 0;

                        if ($currentStock >= $quantity) {
                            $sizeData['stock'] = $currentStock - $quantity;
                            $updated = true;
                            \Log::info("Stock updated for Product #$productId, Size: $size, Reduced by: $quantity, New stock: {$sizeData['stock']}");
                        } else {
                            \Log::warning("Insufficient stock for Product #$productId, Size: $size. Available: $currentStock, Requested: $quantity");
                        }
                        break;
                    }
                }

                if (!$updated) {
                    \Log::error("Size '$size' not found for Product #$productId");
                }
            } else {
                // Fallback: reduce from first available size if no specific size provided
                $quantityToReduce = $quantity;

                foreach ($sizes as &$sizeData) {
                    $currentStock = isset($sizeData['stock']) ? (int)$sizeData['stock'] : 0;

                    if ($currentStock > 0) {
                        $reduceAmount = min($currentStock, $quantityToReduce);
                        $sizeData['stock'] = $currentStock - $reduceAmount;
                        $quantityToReduce -= $reduceAmount;
                        $updated = true;

                        $sizeName = isset($sizeData['size']) ? $sizeData['size'] : (isset($sizeData['name']) ? $sizeData['name'] : 'Unknown');
                        \Log::info("Stock updated for Product #$productId, Size: $sizeName, Reduced by: $reduceAmount, New stock: {$sizeData['stock']}");

                        if ($quantityToReduce <= 0) {
                            break;
                        }
                    }
                }

                if ($quantityToReduce > 0) {
                    \Log::warning("Insufficient stock for Product #$productId: Couldn't reduce full quantity. Remaining: $quantityToReduce");
                }
            }

            // Save the updated sizes back to the product
            if ($updated) {
                // Ensure we save as JSON string
                $product->sizes = is_array($sizes) ? json_encode($sizes) : $sizes;
                $product->save();
                \Log::info("Stock reduction completed for Product #$productId");
            }

        } catch (\Exception $e) {
            \Log::error("Error updating stock for Product #$productId: " . $e->getMessage());
        }
    }

    /**
     * Get all POS customers with their order items.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCustomers()
    {
        try {
            $customers = PosCustomer::with(['orderItems' => function($query) {
                $query->select('id', 'pos_customer_id', 'product_name', 'quantity', 'price', 'tax_amount', 'subtotal', 'created_at');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($customer) {
                // Calculate total amount from order items
                $totalAmount = $customer->orderItems->sum('subtotal');

                // Get the latest order date
                $latestOrderDate = $customer->orderItems->max('created_at');

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'amount' => $totalAmount,
                    'order_date' => $latestOrderDate,
                    'orderItems' => $customer->orderItems
                ];
            });

            return response()->json($customers);
        } catch (\Exception $e) {
            \Log::error('Error fetching POS customers: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer's order history by phone number.
     *
     * @param  string  $phone
     * @return \Illuminate\Http\Response
     */
    public function getCustomerOrders($phone)
    {
        try {
            $customer = PosCustomer::where('phone', $phone)
                ->with(['orderItems' => function($query) {
                    $query->select('id', 'pos_customer_id', 'product_name', 'quantity', 'price', 'tax_percentage', 'tax_amount', 'subtotal', 'created_at')
                        ->orderBy('created_at', 'desc');
                }])
                ->first();

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            // Calculate totals
            $totalAmount = $customer->orderItems->sum('subtotal');
            $totalTax = $customer->orderItems->sum('tax_amount');
            $subtotal = $totalAmount - $totalTax;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'orderItems' => $customer->orderItems,
                    'total_amount' => $totalAmount,
                    'total_tax' => $totalTax,
                    'subtotal' => $subtotal
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching customer orders: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch customer orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order items for a specific transaction with SKU codes.
     *
     * @param  int  $transactionId
     * @return \Illuminate\Http\Response
     */
    public function getOrderItems($transactionId)
    {
        try {
            // Get order items for this specific transaction with SKU codes
            $orderItems = PosOrderItem::where('transaction_id', $transactionId)
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

            if ($orderItems->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No order items found for this transaction'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'items' => $orderItems
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching order items: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch order items: ' . $e->getMessage()
            ], 500);
        }
    }
}
