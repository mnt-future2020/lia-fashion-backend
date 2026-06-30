<?php

namespace App\Http\Controllers\PublicController;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        // Return all categories (no status filter)
        $categories = Category::all();
        return response()->json($categories);
    }

    public function show($id)
    {
        $category = Category::with('subcategories')->findOrFail($id);
        return response()->json($category);
    }
}
