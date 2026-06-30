<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller
{
    public function index($productId)
    {
        $product = Product::findOrFail($productId);

        $reviews = ProductReview::with('user:id,name')
            ->where('product_id', $productId)
            ->latest()
            ->paginate(5);

        $stats = [
            'average' => ProductReview::where('product_id', $productId)->avg('rating') ?? 0,
            'total' => ProductReview::where('product_id', $productId)->count(),
            'distribution' => [
                5 => ProductReview::where('product_id', $productId)->where('rating', 5)->count(),
                4 => ProductReview::where('product_id', $productId)->where('rating', 4)->count(),
                3 => ProductReview::where('product_id', $productId)->where('rating', 3)->count(),
                2 => ProductReview::where('product_id', $productId)->where('rating', 2)->count(),
                1 => ProductReview::where('product_id', $productId)->where('rating', 1)->count(),
            ]
        ];

        return response()->json([
            'reviews' => $reviews,
            'stats' => $stats
        ]);
    }

    public function store(Request $request, $productId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string'
        ]);

        // Check if user already reviewed this product
        $existingReview = ProductReview::where('user_id', Auth::id())
            ->where('product_id', $productId)
            ->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'You have already reviewed this product'
            ], 422);
        }

        $review = ProductReview::create([
            'user_id' => Auth::id(),
            'product_id' => $productId,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        return response()->json([
            'message' => 'Review added successfully',
            'review' => $review
        ], 201);
    }
}