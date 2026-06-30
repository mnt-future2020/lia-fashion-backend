<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentGatewaySettingController extends Controller
{
    public function index()
    {
        try {
            $settings = PaymentGatewaySetting::all();
            return response()->json([
                'status' => 'success',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payment gateway settings'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'gateway_name' => 'required|string|unique:payment_gateway_settings',
                'key_id' => 'required|string',
                'key_secret' => 'required|string',
                'is_sandbox' => 'boolean',
                'is_active' => 'boolean',
                'webhook_secret' => 'nullable|string',
                'additional_settings' => 'nullable'
            ]);

            $setting = PaymentGatewaySetting::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment gateway settings saved successfully',
                'data' => $setting
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e instanceof ValidationException ? 
                    $e->errors() : 'Failed to save payment gateway settings'
            ], $e instanceof ValidationException ? 422 : 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $setting = PaymentGatewaySetting::findOrFail($id);

            $validated = $request->validate([
                'gateway_name' => 'required|string|unique:payment_gateway_settings,gateway_name,' . $id,
                'key_id' => 'required|string',
                'key_secret' => 'required|string',
                'is_sandbox' => 'boolean',
                'is_active' => 'boolean',
                'webhook_secret' => 'nullable|string',
                'additional_settings' => 'nullable'
            ]);

            // If this setting is being made active, deactivate all others
            if ($validated['is_active'] ?? false) {
                PaymentGatewaySetting::where('id', '!=', $id)
                    ->update(['is_active' => false]);
            }

            $setting->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment gateway settings updated successfully',
                'data' => $setting
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e instanceof ValidationException ? 
                    $e->errors() : 'Failed to update payment gateway settings'
            ], $e instanceof ValidationException ? 422 : 500);
        }
    }

    public function show($id)
    {
        try {
            $setting = PaymentGatewaySetting::findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $setting
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment gateway settings not found'
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $setting = PaymentGatewaySetting::findOrFail($id);
            $setting->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment gateway settings deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete payment gateway settings'
            ], 500);
        }
    }

    public function getActive($gatewayName = null)
    {
        try {
            $setting = PaymentGatewaySetting::getActive($gatewayName);
            
            if (!$setting) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active payment gateway settings found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $setting
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch active payment gateway settings'
            ], 500);
        }
    }
}
