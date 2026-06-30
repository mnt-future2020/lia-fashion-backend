<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subcategory;
use App\Models\Setting;
use Illuminate\Http\Request;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class SubcategoryController extends Controller
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
        $subcategories = Subcategory::with('category')->get();
        return response()->json($subcategories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp',
            'category_id' => 'required|exists:categories,id'
        ]);

        $result = $this->cloudinary->upload(
            $request->file('image')->getRealPath(),
            ['folder' => 'subcategories']
        );

        $subcategory = Subcategory::create([
            'name' => $request->name,
            'image' => $result['secure_url'],
            'category_id' => $request->category_id
        ]);

        return response()->json($subcategory, 201);
    }

    public function update(Request $request, Subcategory $subcategory)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'category_id' => 'required|exists:categories,id'
        ]);

        if ($request->hasFile('image')) {
            $result = $this->cloudinary->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'subcategories']
            );
            $subcategory->image = $result['secure_url'];
        }

        $subcategory->name = $request->name;
        $subcategory->category_id = $request->category_id;
        $subcategory->save();

        return response()->json($subcategory);
    }

    public function destroy(Subcategory $subcategory)
    {
        if ($subcategory->image) {
            $publicId = substr(strrchr(dirname($subcategory->image), '/'), 1) . '/' .
                       basename($subcategory->image, '.' . pathinfo($subcategory->image, PATHINFO_EXTENSION));
            try {
                $this->cloudinary->destroy($publicId);
            } catch (\Exception $e) {
                // Handle deletion error if needed
            }
        }

        $subcategory->delete();
        return response()->json(null, 204);
    }
}
