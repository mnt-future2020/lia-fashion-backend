<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class CategoryController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
        $cloudSettings = [
            'cloud_name' => Setting::getValue('cloudinary_cloud_name'),
            'api_key' => Setting::getValue('cloudinary_api_key'),
            'api_secret' => Setting::getValue('cloudinary_api_secret')
        ];

        Configuration::instance([
            'cloud' => $cloudSettings
        ]);
        $this->cloudinary = new UploadApi();
    }

    public function index()
    {
        // $categories = Category::all();
         $categories = Category::with('subcategories')->get();
        return response()->json($categories);
    }

    // Get category by slug or ID
    public function getBySlugOrId($slugOrId)
    {
        // Find by ID only
        if (is_numeric($slugOrId)) {
            $category = Category::find($slugOrId);
        } else {
            $category = null;
        }

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category);
    }

    // Get subcategories by category slug/ID and optionally filter by subcategory slug
    public function getSubcategoriesBySlugOrId($categorySlugOrId, $subcategorySlug = null)
    {
        // Find category by ID only
        if (is_numeric($categorySlugOrId)) {
            $category = Category::find($categorySlugOrId);
        } else {
            $category = null;
        }

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $subcategoriesQuery = $category->subcategories();

        return response()->json($subcategoriesQuery->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp'
        ]);

        $result = $this->cloudinary->upload(
            $request->file('image')->getRealPath(),
            ['folder' => 'categories']
        );

        $category = Category::create([
            'name' => $request->name,
            'image' => $result['secure_url']
        ]);

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp'
        ]);

        if ($request->hasFile('image')) {
            // Upload new image to Cloudinary
            $result = $this->cloudinary->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'categories']
            );
            $category->image = $result['secure_url'];
        }

        $category->name = $request->name;
        $category->save();

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        // Delete from Cloudinary if image exists
        if ($category->image) {
            // Extract public_id from URL
            $publicId = substr(strrchr(dirname($category->image), '/'), 1) . '/' . basename($category->image, '.' . pathinfo($category->image, PATHINFO_EXTENSION));
            try {
                $this->cloudinary->destroy($publicId);
            } catch (\Exception $e) {
                // Handle deletion error if needed
            }
        }

        $category->delete();
        return response()->json(null, 204);
    }

    public function subcategories($categoryId)
    {
        $category = Category::findOrFail($categoryId);
        $subcategories = $category->subcategories;
        return response()->json($subcategories);
    }
}
