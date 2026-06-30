<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminReviewController extends Controller
{
    public function index()
    {
        $reviews = ProductReview::with(['user:id,name,email', 'product:id,name'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'reviews' => $reviews
        ]);
    }

    public function stats()
    {
        $totalReviews = ProductReview::count();
        $averageRating = ProductReview::avg('rating') ?? 0;
        $positiveReviews = ProductReview::where('rating', '>=', 4)->count();
        $negativeReviews = ProductReview::where('rating', '<=', 2)->count();

        return response()->json([
            'totalReviews' => $totalReviews,
            'averageRating' => $averageRating,
            'positiveReviews' => $positiveReviews,
            'negativeReviews' => $negativeReviews
        ]);
    }

    public function destroy(ProductReview $review)
    {
        $review->delete();
        return response()->json(['message' => 'Review deleted successfully']);
    }

    public function overallStats()
    {
        $stats = ProductReview::select(
            DB::raw('COUNT(*) as totalReviews'),
            DB::raw('AVG(rating) as averageRating')
        )->first();

        return response()->json([
            'totalReviews' => $stats->totalReviews,
            'averageRating' => number_format($stats->averageRating, 1)
        ]);
    }

    public function timelineStats(Request $request)
    {
        $filter = $request->query('filter', '7days');

        $startDate = match($filter) {
            '7days' => Carbon::now()->subDays(7),
            '30days' => Carbon::now()->subDays(30),
            '90days' => Carbon::now()->subDays(90),
            default => Carbon::now()->subDays(7)
        };

        $stats = ProductReview::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(CASE WHEN rating >= 4 THEN 1 END) as positiveCount'),
            DB::raw('COUNT(CASE WHEN rating <= 2 THEN 1 END) as negativeCount')
        )
        ->where('created_at', '>=', $startDate)
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        return response()->json($stats);
    }

    public function getPositiveReviews()
    {
        $reviews = ProductReview::with(['user:id,name', 'product:id,name'])
            ->where('rating', '>=', 4)
            ->latest()
            ->take(7)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'name' => $review->user->name,
                    'rating' => $review->rating,
                    'text' => $review->comment,
                    'image' => $review->user->profile_image ?? '/assets/circle/circle.png',
                    'product_name' => $review->product->name
                ];
            });

        return response()->json($reviews);
    }
}
