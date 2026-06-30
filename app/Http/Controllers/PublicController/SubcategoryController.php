<?php

namespace App\Http\Controllers\PublicController;

use App\Http\Controllers\Controller;
use App\Models\Subcategory;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Subcategory::query()->with('category');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->get('category_id'));
            }

            // Optional ordering
            $query->latest();

            // Optional simple pagination/limit
            $limit = (int) $request->get('limit', 0);

            $subcategories = $limit > 0
                ? $query->take($limit)->get()
                : $query->get();

            $transformed = $subcategories->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'image' => $s->image,
                    'category_id' => $s->category_id,
                    'category' => $s->category ? [
                        'id' => $s->category->id,
                        'name' => $s->category->name,
                    ] : null,
                ];
            });

            return response()->json($transformed->values());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}


