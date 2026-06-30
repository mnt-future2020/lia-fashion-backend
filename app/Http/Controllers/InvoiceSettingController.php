<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InvoiceSettingController extends Controller
{
    /**
     * Get all invoice settings
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $settings = InvoiceSetting::all();
        return response()->json(['data' => $settings]);
    }

    /**
     * Get active invoice setting
     *
     * @return \Illuminate\Http\Response
     */
    public function getActiveSetting()
    {
        $setting = InvoiceSetting::where('is_active', true)->first();

        if (!$setting) {
            return response()->json([
                'message' => 'No active invoice setting found'
            ], 404);
        }

        return response()->json(['data' => $setting]);
    }

    /**
     * Store a new invoice setting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prefix' => 'required|string|max:10',
            'financial_year_start' => 'required|date',
            'financial_year_end' => 'required|date|after:financial_year_start',
            'last_sequence_number' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // If this is set as active, deactivate all other settings
        if ($request->input('is_active', false)) {
            InvoiceSetting::where('is_active', true)->update(['is_active' => false]);
        }

        // Format the initial invoice number based on the prefix and financial year
        $fyStartYear = Carbon::parse($request->input('financial_year_start'))->format('y');
        $fyEndYear = Carbon::parse($request->input('financial_year_end'))->format('y');
        $prefix = $request->input('prefix');
        $sequence = $request->input('last_sequence_number', 0);

        $lastInvoiceNumber = $prefix . $fyStartYear . $fyEndYear . str_pad($sequence, 4, '0', STR_PAD_LEFT);

        $setting = InvoiceSetting::create([
            'prefix' => $prefix,
            'financial_year_start' => $request->input('financial_year_start'),
            'financial_year_end' => $request->input('financial_year_end'),
            'last_invoice_number' => $lastInvoiceNumber,
            'last_sequence_number' => $sequence,
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'message' => 'Invoice setting created successfully',
            'data' => $setting
        ], 201);
    }

    /**
     * Get a specific invoice setting
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $setting = InvoiceSetting::find($id);

        if (!$setting) {
            return response()->json([
                'message' => 'Invoice setting not found'
            ], 404);
        }

        return response()->json(['data' => $setting]);
    }

    /**
     * Update an invoice setting
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $setting = InvoiceSetting::find($id);

        if (!$setting) {
            return response()->json([
                'message' => 'Invoice setting not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'prefix' => 'string|max:10',
            'financial_year_start' => 'date',
            'financial_year_end' => 'date|after:financial_year_start',
            'last_sequence_number' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // If this is being set as active, deactivate all other settings
        if ($request->has('is_active') && $request->input('is_active')) {
            InvoiceSetting::where('id', '!=', $id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $setting->update($request->all());

        return response()->json([
            'message' => 'Invoice setting updated successfully',
            'data' => $setting
        ]);
    }

    /**
     * Delete an invoice setting
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $setting = InvoiceSetting::find($id);

        if (!$setting) {
            return response()->json([
                'message' => 'Invoice setting not found'
            ], 404);
        }

        // Don't allow deleting active setting
        if ($setting->is_active) {
            return response()->json([
                'message' => 'Cannot delete active invoice setting'
            ], 400);
        }

        $setting->delete();

        return response()->json([
            'message' => 'Invoice setting deleted successfully'
        ]);
    }

    /**
     * Generate new invoice number
     *
     * @return \Illuminate\Http\Response
     */
    public function generateInvoiceNumber()
    {
        $setting = InvoiceSetting::where('is_active', true)->first();

        if (!$setting) {
            return response()->json([
                'message' => 'No active invoice setting found'
            ], 404);
        }

        // Check if current date is within the financial year
        $today = Carbon::today();
        if ($today->lt($setting->financial_year_start) || $today->gt($setting->financial_year_end)) {
            return response()->json([
                'message' => 'Current date is outside the configured financial year'
            ], 400);
        }

        // Increment sequence number
        $newSequence = $setting->last_sequence_number + 1;

        // Format new invoice number
        $fyStartYear = Carbon::parse($setting->financial_year_start)->format('y');
        $fyEndYear = Carbon::parse($setting->financial_year_end)->format('y');
        $newInvoiceNumber = $setting->prefix . $fyStartYear . $fyEndYear . str_pad($newSequence, 4, '0', STR_PAD_LEFT);

        // Update the setting
        $setting->update([
            'last_invoice_number' => $newInvoiceNumber,
            'last_sequence_number' => $newSequence
        ]);

        return response()->json([
            'invoice_number' => $newInvoiceNumber,
            'sequence_number' => $newSequence
        ]);
    }
}
