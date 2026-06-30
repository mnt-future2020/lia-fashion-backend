<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShiprocketSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShiprocketSettingController extends Controller
{    public function index()
    {
        try {
            $settings = ShiprocketSetting::latest()->first();            
            return response()->json([
                'status' => 'success',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch Shiprocket settings'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ], 422);
            }

            // Deactivate all existing settings
            ShiprocketSetting::where('is_active', true)->update(['is_active' => false]);

            // Create new settings
            $settings = ShiprocketSetting::create($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Shiprocket settings saved successfully',
                'data' => $settings
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save Shiprocket settings'
            ], 500);
        }
    }    public function update(Request $request, $id)
    {
        try {
            // Only require password if it's being changed
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => $request->has('password') ? 'string|min:6' : '',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $settings = ShiprocketSetting::findOrFail($id);
              $settings->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Shiprocket settings updated successfully',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update Shiprocket settings'
            ], 500);
        }
    }
}
