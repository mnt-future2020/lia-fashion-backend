<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class VendorController extends Controller
{
    /**
     * Display a listing of vendors.
     */
    public function index(Request $request)
    {
        $query = Vendor::with('purchaseEntries');

        // Apply search if provided
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Apply status filter if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Get paginated results
        $vendors = $query->latest()->paginate(10);

        // Update summary fields for each vendor
        foreach ($vendors as $vendor) {
            $vendor->total_orders = $vendor->purchaseEntries->count();
            $vendor->total_amount = $vendor->purchaseEntries->sum('total_cost');
            $vendor->last_purchase_date = optional($vendor->purchaseEntries->sortByDesc('purchase_date')->first())->purchase_date;
        }

        return response()->json([
            'status' => 'success',
            'data' => $vendors
        ]);
    }

    /**
     * Store a newly created vendor.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_name' => 'required|string|max:255',
                'contact_person_name' => 'required|string|max:255',
                'gst_number' => 'nullable|string|max:255',
                'email' => 'required|email|unique:vendors,email',
                'phone_number' => 'required|string|max:20',
                'category' => 'nullable|string|max:255',
                'address_line1' => 'required|string',
                'city' => 'required|string|max:255',
                'district' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'pincode' => 'required|string|max:20',
                'status' => 'required|in:active,inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendor = Vendor::create($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor created successfully',
                'data' => $vendor
            ], 201);
        } catch (\Exception $e) {
            Log::error('Vendor creation failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create vendor. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified vendor with purchase entries.
     */
    public function show($id)
    {
        $vendor = Vendor::with('purchaseEntries')->findOrFail($id);
        $vendor->total_orders = $vendor->purchaseEntries->count();
        $vendor->total_amount = $vendor->purchaseEntries->sum('total_cost');
        $vendor->last_purchase_date = optional($vendor->purchaseEntries->sortByDesc('purchase_date')->first())->purchase_date;
        return response()->json([
            'status' => 'success',
            'data' => $vendor
        ]);
    }

    /**
     * Update the specified vendor.
     */
    public function update(Request $request, Vendor $vendor)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_name' => 'required|string|max:255',
                'contact_person_name' => 'required|string|max:255',
                'gst_number' => 'nullable|string|max:255',
                'email' => 'required|email|unique:vendors,email,' . $vendor->id,
                'phone_number' => 'required|string|max:20',
                'category' => 'nullable|string|max:255',
                'address_line1' => 'required|string',
                'city' => 'required|string|max:255',
                'district' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'pincode' => 'required|string|max:20',
                'status' => 'required|in:active,inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendor->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor updated successfully',
                'data' => $vendor
            ]);
        } catch (\Exception $e) {
            Log::error('Vendor update failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update vendor. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified vendor.
     */
    public function destroy(Vendor $vendor)
    {
        try {
            $vendor->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Vendor deletion failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete vendor. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
