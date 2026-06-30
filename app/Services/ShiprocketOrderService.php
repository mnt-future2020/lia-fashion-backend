<?php

namespace App\Services;

use App\Models\ShiprocketSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketOrderService
{
    private $token;
    private $baseUrl = 'https://apiv2.shiprocket.in/v1/external';

    public function __construct()
    {
        $this->authenticate();
    }

    private function authenticate()
    {
        try {
            $settings = ShiprocketSetting::where('is_active', true)->first();

            if (!$settings) {
                Log::warning('Shiprocket settings not found or inactive');
                return;
            }

            Log::info('Attempting Shiprocket authentication', [
                'email' => $settings->email,
                'active' => $settings->is_active
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/auth/login', [
                'email' => $settings->email,
                'password' => $settings->password
            ]);

            Log::info('Shiprocket auth response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                $this->token = $response->json('token');
                if ($this->token) {
                    Log::info('Shiprocket authentication successful');
                } else {
                    Log::error('Shiprocket token not found in response');
                    throw new \Exception('Authentication successful but no token received');
                }
            } else {
                Log::error('Shiprocket authentication failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new \Exception('Authentication failed: ' . ($response->json('message') ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('Shiprocket authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to handle in createOrder
        }
    }

    public function createOrder($orderData, $transactionId)
    {
        try {
            if (!$this->token) {
                Log::warning('No Shiprocket token available, attempting re-authentication');
                $this->authenticate();

                if (!$this->token) {
                    throw new \Exception('Failed to authenticate with Shiprocket');
                }
            }

            // Format data according to Shiprocket's required structure
            $formattedOrder = [
                'order_id' => (string)$transactionId,
                'order_date' => now()->format('Y-m-d'),
                'pickup_location' => 'Lia',
                'channel_id' => '',
                'billing_customer_name' => $orderData['name'],
                'billing_last_name' => '',
                'billing_address' => $orderData['address'],
                'billing_address_2' => '',
                'billing_city' => $orderData['city'],
                'billing_pincode' => $orderData['pin_code'],
                'billing_state' => $orderData['state'],
                'billing_country' => $orderData['country'] ?? 'India',
                'billing_phone' => $orderData['phone'],
                'billing_email' => $orderData['email'],
                'shipping_is_billing' => true,
                'order_items' => array_map(function($item) {
                    return [
                        'name' => $item['name'] ?? '',
                        'sku' => (string)($item['sku'] ?? ''),
                        'units' => (int)($item['units'] ?? 1),
                        'selling_price' => (string)($item['selling_price'] ?? '0'),
                        'discount' => '',
                        'tax' => '',
                        'hsn' => ''
                    ];
                }, $orderData['order_items']),
                'payment_method' => 'Prepaid',
                'shipping_charges' => 0,
                'giftwrap_charges' => 0,
                'transaction_charges' => 0,
                'total_discount' => 0,
                'sub_total' => $orderData['sub_total'],
                'length' => 10,
                'breadth' => 15,
                'height' => 10,
                'weight' => 0.5
            ];

            // Add debug logging before sending to API
            Log::info('Formatted Shiprocket order data:', [
                'order_data' => $formattedOrder,
                'original_items' => $orderData['order_items']
            ]);

            Log::info('Creating Shiprocket order', [
                'transaction_id' => $transactionId,
                'formatted_data' => $formattedOrder
            ]);

            $response = Http::withToken($this->token)
                ->withHeaders([
                    'Content-Type' => 'application/json'
                ])
                ->post($this->baseUrl . '/orders/create/adhoc', $formattedOrder);

            Log::info('Shiprocket API response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Shiprocket API error: ' . ($response->json('message') ?? 'Unknown error'));
            }

            $responseData = $response->json();

            if (empty($responseData['shipment_id'])) {
                throw new \Exception('No shipment ID received from Shiprocket');
            }

            return [
                'status' => 'success',
                'data' => $responseData,
                'shipment_id' => $responseData['shipment_id']
            ];

        } catch (\Exception $e) {
            Log::error('Shiprocket order creation error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
