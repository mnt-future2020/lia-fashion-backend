<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::latest()->get()->map(function($coupon) {
            return [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'description' => $coupon->description,
                'discount_value' => $coupon->discount_value,
                'discount_type' => $coupon->discount_type,
                'min_order_value' => $coupon->min_order_value,
                'min_purchase_limit' => $coupon->min_purchase_limit,
                'usage_count' => $coupon->usage_count,
                'max_usage' => $coupon->max_usage,
                'start_date' => $coupon->start_date?->format('Y-m-d'),
                'end_date' => $coupon->end_date?->format('Y-m-d'),
                'is_active' => $coupon->is_active,
                'status' => $this->getCouponStatus($coupon)
            ];
        });

        return response()->json($coupons);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:coupons|max:50',
            'name' => 'required|max:255',
            'description' => 'nullable|string',
            'discount_value' => 'required|numeric|min:0',
            'discount_type' => 'required|in:amount,percentage',
            'min_order_value' => 'required|integer|min:0',
            'min_purchase_limit' => 'required|integer|min:0',
            'max_usage' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Validate percentage cannot be more than 100%
        if ($request->discount_type === 'percentage' && $request->discount_value > 100) {
            return response()->json([
                'message' => 'Percentage discount cannot exceed 100%'
            ], 422);
        }

        // Validate discount amount cannot be more than minimum order value
        if ($request->discount_type === 'amount' && $request->discount_value > $request->min_order_value) {
            return response()->json([
                'message' => 'Discount amount cannot be more than minimum order value'
            ], 422);
        }

        $coupon = Coupon::create([
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'description' => $request->description,
            'discount_value' => $request->discount_value,
            'discount_type' => $request->discount_type,
            'min_order_value' => $request->min_order_value,
            'min_purchase_limit' => $request->min_purchase_limit,
            'max_usage' => $request->max_usage,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true,
            'usage_count' => 0
        ]);

        return response()->json($coupon, 201);
    }

    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'order_amount' => 'required|numeric|min:0',
            'user_id' => 'required|exists:users,id'
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        // Better error handling for debugging
        if (!$coupon) {
            // Let's also check if any coupons exist at all
            $totalCoupons = Coupon::count();
            return response()->json([
                'message' => "Coupon code '{$request->code}' not found. Total coupons in database: {$totalCoupons}",
                'debug_info' => [
                    'searched_code' => $request->code,
                    'total_coupons' => $totalCoupons,
                    'all_codes' => Coupon::pluck('code')->toArray()
                ]
            ], 422);
        }

        // Comprehensive validation with detailed error messages
        if (!$coupon->is_active) {
            return response()->json(['message' => 'This coupon is currently inactive'], 422);
        }

        // Check date range validation
        $now = Carbon::now();

        if ($coupon->start_date && $now->lt($coupon->start_date)) {
            return response()->json(['message' => 'This coupon is not yet valid'], 422);
        }

        if ($coupon->end_date && $now->gt($coupon->end_date)) {
            return response()->json(['message' => 'This coupon has expired'], 422);
        }

        // Check redemption status - only unused coupons can be applied
        // Note: redemption_status should NOT be set to 'used' until payment completion
        if (isset($coupon->redemption_status) && $coupon->redemption_status === 'used') {
            return response()->json(['message' => 'This coupon has already been redeemed'], 422);
        }

        // Check if user has already used this coupon
        if ($coupon->hasBeenUsedByUser($request->user_id)) {
            return response()->json(['message' => 'You have already used this coupon'], 409);
        }

        // Check maximum usage limit
        if ($coupon->max_usage && $coupon->usage_count >= $coupon->max_usage) {
            return response()->json(['message' => 'This coupon has reached its usage limit and is no longer available'], 410);
        }

        // Check minimum order value
        if ($request->order_amount < $coupon->min_order_value) {
            return response()->json([
                'message' => "Minimum order value of ₹{$coupon->min_order_value} required. Current total: ₹" . number_format($request->order_amount, 2)
            ], 422);
        }

        // Calculate discount based on type
        $discount = $this->calculateDiscount($coupon, $request->order_amount);

        return response()->json([
            'valid' => true,
            'discount' => $discount,
            'final_amount' => $request->order_amount - $discount,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'description' => $coupon->description,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
                'min_order_value' => $coupon->min_order_value,
                'min_purchase_limit' => $coupon->min_purchase_limit,
                'start_date' => $coupon->start_date?->format('Y-m-d'),
                'end_date' => $coupon->end_date?->format('Y-m-d'),
                'usage_count' => $coupon->usage_count,
                'max_usage' => $coupon->max_usage
            ]
        ]);
    }

    // Add method to record coupon usage
    public function recordUsage($couponId, $userId)
    {
        $coupon = Coupon::findOrFail($couponId);

        if (!$coupon->hasBeenUsedByUser($userId)) {
            $coupon->users()->attach($userId);
            $coupon->increment('usage_count');
        }
    }

    // Method to mark coupon as used after successful payment
    public function markAsUsed($couponId, $userId)
    {
        $coupon = Coupon::findOrFail($couponId);

        // Only mark as used if payment was successful
        if (!$coupon->hasBeenUsedByUser($userId)) {
            // Record the usage relationship
            $coupon->users()->attach($userId);
            $coupon->increment('usage_count');

            // // Mark coupon as used only after successful payment
            // $coupon->update(['redemption_status' => 'used']);
        }

        return $coupon;
    }

    public function toggleStatus($id)
    {
        try {
            $coupon = Coupon::findOrFail($id);
            $coupon->is_active = !$coupon->is_active;
            $coupon->save();

            return response()->json([
                'status' => 'success',
                'is_active' => $coupon->is_active,
                'message' => $coupon->is_active ? 'Coupon activated' : 'Coupon deactivated'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update coupon status'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $coupon = Coupon::findOrFail($id);
            return response()->json($coupon);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $coupon = Coupon::findOrFail($id);

            $request->validate([
                'code' => 'required|unique:coupons,code,'.$id.'|max:50',
                'name' => 'required|max:255',
                'description' => 'nullable|string',
                'discount_value' => 'required|numeric|min:0',
                'discount_type' => 'required|in:amount,percentage',
                'min_order_value' => 'required|integer|min:0',
                'min_purchase_limit' => 'required|integer|min:0',
                'max_usage' => 'required|integer|min:1',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Validate percentage cannot be more than 100%
            if ($request->discount_type === 'percentage' && $request->discount_value > 100) {
                return response()->json(['message' => 'Percentage discount cannot exceed 100%'], 422);
            }

            // Validate discount amount cannot be more than minimum order value
            if ($request->discount_type === 'amount' && $request->discount_value > $request->min_order_value) {
                return response()->json(['message' => 'Discount amount cannot be more than minimum order value'], 422);
            }

            $coupon->update([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'discount_value' => $request->discount_value,
                'discount_type' => $request->discount_type,
                'min_order_value' => $request->min_order_value,
                'min_purchase_limit' => $request->min_purchase_limit,
                'max_usage' => $request->max_usage,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            return response()->json($coupon);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update coupon'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $coupon = Coupon::findOrFail($id);
            $coupon->delete();
            return response()->json(['message' => 'Coupon deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete coupon'], 500);
        }
    }

    private function calculateDiscount($coupon, $amount)
    {
        if ($coupon->discount_type === 'percentage') {
            // Case 2: Percentage-Based Coupon
            $discount = ($amount * $coupon->discount_value) / 100;

            // Apply cap if specified (min_purchase_limit acts as max discount cap)
            if ($coupon->min_purchase_limit && $discount > $coupon->min_purchase_limit) {
                $discount = $coupon->min_purchase_limit;
            }

            return round($discount, 2);
        } else {
            // Case 1: Fixed Amount Coupon
            // Ensure discount doesn't exceed the order amount
            $discount = min($coupon->discount_value, $amount);

            // Apply cap if specified
            if ($coupon->min_purchase_limit) {
                $discount = min($discount, $coupon->min_purchase_limit);
            }

            return round($discount, 2);
        }
    }

    private function isCouponValid($coupon)
    {
        if (!$coupon->is_active) return false;

        $now = Carbon::now();

        if ($coupon->start_date && $now->lt($coupon->start_date)) return false;
        if ($coupon->end_date && $now->gt($coupon->end_date)) return false;

        return true;
    }

    private function getCouponStatus($coupon)
    {
        if (!$coupon->is_active) return 'Inactive';
        if ($coupon->end_date && Carbon::now()->gt($coupon->end_date)) return 'Expired';
        if ($coupon->start_date && Carbon::now()->lt($coupon->start_date)) return 'Scheduled';
        return 'Active';
    }
}