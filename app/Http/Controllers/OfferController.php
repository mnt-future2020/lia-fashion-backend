<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OfferController extends Controller
{
    public function index()
    {
        $offers = Offer::with(['category', 'subcategory'])
            ->get()
            ->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'category_id' => $offer->category_id,
                    'category_name' => $offer->category ? $offer->category->name : null,
                    'subcategory_id' => $offer->subcategory_id,
                    'subcategory_name' => $offer->subcategory ? $offer->subcategory->name : null,
                    'items_count' => $offer->items_count,
                    'discount_amount' => $offer->discount_amount,
                ];
            });
        return response()->json(['status' => 'success', 'data' => $offers]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|exists:categories,id',
            'subcategory' => 'required|exists:subcategories,id',
            'items_count' => 'required|integer|min:1',
            'discount_amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }
        $offer = Offer::create([
            'category_id' => $request->category,
            'subcategory_id' => $request->subcategory,
            'items_count' => $request->items_count,
            'discount_amount' => $request->discount_amount,
        ]);
        return response()->json(['status' => 'success', 'data' => $offer]);
    }

    public function update(Request $request, $id)
    {
        $offer = Offer::find($id);
        if (!$offer) {
            return response()->json(['status' => 'error', 'message' => 'Offer not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'category' => 'required|exists:categories,id',
            'subcategory' => 'required|exists:subcategories,id',
            'items_count' => 'required|integer|min:1',
            'discount_amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }
        $offer->update([
            'category_id' => $request->category,
            'subcategory_id' => $request->subcategory,
            'items_count' => $request->items_count,
            'discount_amount' => $request->discount_amount,
        ]);
        return response()->json(['status' => 'success', 'data' => $offer]);
    }

    public function destroy($id)
    {
        $offer = Offer::find($id);
        if (!$offer) {
            return response()->json(['status' => 'error', 'message' => 'Offer not found'], 404);
        }
        $offer->delete();
        return response()->json(['status' => 'success', 'message' => 'Offer deleted successfully']);
    }
}
