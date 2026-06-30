<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingRuleController extends Controller
{
    /**
     * Display a listing of the shipping rules.
     */
    public function index(Request $request)
    {
        $type = $request->input('type', 'weight');
        $perPage = $request->input('per_page', 10);

        $rules = ShippingRule::where('type', $type)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $rules,
        ]);
    }

    /**
     * Store a newly created shipping rule.
     */
    public function store(Request $request)
    {
        // Validate common fields
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:weight,location',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Validate type-specific fields
        if ($request->type === 'weight') {
            $weightValidator = Validator::make($request->all(), [
                'from_weight' => 'required|numeric|min:0',
                'to_weight' => 'required|numeric|gt:from_weight',
                'free_shipping_amount' => 'required|numeric|min:0',
                'price' => 'required|numeric|min:0',
            ]);

            if ($weightValidator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $weightValidator->errors()->first(),
                ], 422);
            }
        } else {
            $locationValidator = Validator::make($request->all(), [
                'location' => 'required|string|max:255',
                'shipping_charge' => 'required|numeric|min:0',
                'estimated_days' => 'required|string|max:255',
            ]);

            if ($locationValidator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $locationValidator->errors()->first(),
                ], 422);
            }
        }

        // Create shipping rule
        $shippingRule = ShippingRule::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Shipping rule created successfully',
            'data' => $shippingRule,
        ], 201);
    }

    /**
     * Display the specified shipping rule.
     */
    public function show(ShippingRule $shippingRule)
    {
        return response()->json([
            'status' => 'success',
            'data' => $shippingRule,
        ]);
    }

    /**
     * Update the specified shipping rule.
     */
    public function update(Request $request, ShippingRule $shippingRule)
    {
        // Validate type-specific fields
        if ($shippingRule->type === 'weight') {
            $validator = Validator::make($request->all(), [
                'from_weight' => 'sometimes|required|numeric|min:0',
                'to_weight' => 'sometimes|required|numeric|gt:from_weight',
                'free_shipping_amount' => 'sometimes|required|numeric|min:0',
                'price' => 'sometimes|required|numeric|min:0',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'location' => 'sometimes|required|string|max:255',
                'shipping_charge' => 'sometimes|required|numeric|min:0',
                'estimated_days' => 'sometimes|required|string|max:255',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Update shipping rule
        $shippingRule->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Shipping rule updated successfully',
            'data' => $shippingRule,
        ]);
    }

    /**
     * Remove the specified shipping rule.
     */
    public function destroy(ShippingRule $shippingRule)
    {
        $shippingRule->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Shipping rule deleted successfully',
        ]);
    }
}
