<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class PurchaseEntryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'purchase_no' => 'required|string|unique:purchase_entries,purchase_no',
            'purchase_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            // Save the purchase entry (header)
            $purchaseEntry = DB::table('purchase_entries')->insertGetId([
                'vendor_id' => $request->vendor_id,
                'purchase_no' => $request->purchase_no,
                'purchase_date' => $request->purchase_date,
                'product_name' => implode(', ', array_column($request->products, 'product_name')),
                'quantity' => implode(', ', array_column($request->products, 'quantity')),
                'unit_price' => implode(', ', array_column($request->products, 'unit_price')),
                'total_cost' => $request->total_amount ?? 0,
                'discount' => $request->discount ?? 0,
                'notes' => $request->notes ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Save each product (item)
            foreach ($request->products as $item) {
                DB::table('purchase_entry_items')->insert([
                    'purchase_entry_id' => $purchaseEntry,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'sku_code' => $item['sku_code'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'gst_rate' => $item['gst_rate'] ?? 0,
                    'total_price' => $item['total_cost'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Purchase entry saved and stock updated']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function showByNumber($purchase_no)
    {
        $entry = \App\Models\PurchaseEntry::with('vendor')->where('purchase_no', $purchase_no)->first();
        if (!$entry) {
            return response()->json(['status' => 'error', 'message' => 'Purchase entry not found'], 404);
        }

        // Get purchase entry items
        $items = DB::table('purchase_entry_items')
            ->where('purchase_entry_id', $entry->id)
            ->select('id', 'product_name', 'quantity', 'unit_price', 'total_price', 'gst_rate')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $entry,
            'items' => $items
        ]);
    }
}
