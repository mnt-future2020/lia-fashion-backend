<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteReview;
use Illuminate\Http\Request;

class SiteReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = SiteReview::query();

        if ($request->filled('approved')) {
            $query->where('is_approved', filter_var($request->approved, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('text', 'like', "%{$searchTerm}%");
            });
        }

        $reviews = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json($reviews);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'is_approved' => 'required|boolean',
        ]);

        $review = SiteReview::findOrFail($id);
        $review->is_approved = $request->boolean('is_approved');
        $review->save();

        return response()->json(['message' => 'Review updated', 'review' => $review]);
    }

    public function destroy($id)
    {
        $review = SiteReview::findOrFail($id);
        $review->delete();

        return response()->json(['message' => 'Review deleted']);
    }
}


