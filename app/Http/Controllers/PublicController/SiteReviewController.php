<?php

namespace App\Http\Controllers\PublicController;

use App\Http\Controllers\Controller;
use App\Models\SiteReview;
use Illuminate\Http\Request;

class SiteReviewController extends Controller
{
    public function index()
    {
        $reviews = SiteReview::query()
            ->where('is_approved', true)
            ->latest()
            ->get(['id', 'name', 'rating', 'text', 'created_at']);

        return response()->json($reviews);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'rating' => 'required|integer|min:1|max:5',
            'text' => 'required|string|max:1000',
        ]);

        $review = SiteReview::create([
            'name' => $validated['name'],
            'rating' => $validated['rating'],
            'text' => $validated['text'],
            'is_approved' => true, // auto-approve; change to false if moderation required
        ]);

        return response()->json($review, 201);
    }
}



