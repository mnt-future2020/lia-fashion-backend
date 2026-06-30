<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\Setting;
use Illuminate\Http\Request;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class ProductController extends Controller
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

    /**
     * Display a listing of products.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Remove auth check to make it public
            $products = Product::with(['colors', 'category', 'subcategory'])
                ->latest()
                ->get()
                ->map(function($product) {
                    $color = $product->colors->first();
                    $mainImage = $color ? $color->cover_image : null;
                    $otherImages = $color ? $color->other_images : [];
                    $sizes = $product->sizes;
                    if (!is_array($sizes)) {
                        $sizes = json_decode($sizes, true) ?: [];
                    }

                    // Calculate total stock
                    $totalStock = array_reduce($sizes, function($carry, $size) {
                        return $carry + (int)($size['stock'] ?? 0);
                    }, 0);

                    $status = 'Out of Stock';
                    if ($totalStock > 0) {
                        $status = $product->is_published ? 'Active' : 'Inactive';
                    }

                    // Format sizes with stock count
                    $sizesList = array_map(function($size) {
                        return $size['size'] . ' (' . $size['stock'] . ')';
                    }, $sizes);

                    // Get prices for each size
                    $sizePrices = array_map(function($size) {
                        return [
                            'size' => $size['size'],
                            'price' => isset($size['selling_price']) ? (float)$size['selling_price'] : 0
                        ];
                    }, $sizes);

                    // Get base price (price of first size)
                    $basePrice = !empty($sizes) && isset($sizes[0]['selling_price'])
                        ? (float)$sizes[0]['selling_price']
                        : 0;

                    return [
                        'id' => $product->id,
                        'image' => $mainImage,
                        'other_images' => $otherImages,
                        'name' => $product->name,
                        'category' => $product->category->name,
                        'subcategory' => $product->subcategory ? $product->subcategory->name : null,
                        'subcategory_id' => $product->subcategory_id,
                        'sku_code' => $product->sku_code,
                        'stock' => $totalStock > 0 ? $totalStock : 0,
                        'isLowStock' => $totalStock > 0 && $totalStock <= 20,
                        'color' => $product->colors->pluck('color')->implode(', '),
                        'sizes' => implode(', ', $sizesList),
                        'size_prices' => $sizePrices, // Add size prices array
                        'price' => '₹' . number_format($basePrice, 2),
                        'tax_percentage' => $product->tax_percentage,
                        'badge' => $product->badge,
                        'created_at' => $product->created_at->format('d M Y'),
                        'status' => $status,
                    ];
                });

            return response()->json($products);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

public function store(Request $request)
    {
        try {
            // First validate all fields including SKU uniqueness
            $validator = \Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'sku_code' => 'required|string|unique:products,sku_code',
                'description' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'subcategory_id' => 'required|exists:subcategories,id',
                'sizes' => 'required|json',
                'tax_percentage' => 'nullable|numeric',
                'min_quantity_for_discount' => 'nullable|integer|min:2',
                'discounted_price' => 'nullable|numeric|min:0',
                'badge' => 'nullable|string|in:New arrival,Best Seller,Hot Selling,Trending,Limited,Premium',
                'weight' => 'nullable|numeric',
                'weight_unit' => 'required|in:gram,kg',
                'colors.*.color' => 'required|string',
                'colors.*.cover_image' => 'required|image',
                'colors.*.other_images.*' => 'nullable|image'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Begin transaction after validation passes
            return \DB::transaction(function () use ($request) {

                // Create product with validated data
                try {
                    $product = Product::create([
                        'name' => $request->name,
                        'sku_code' => $request->sku_code,
                        'description' => $request->description,
                        'category_id' => $request->category_id,
                        'subcategory_id' => $request->subcategory_id,
                        'sizes' => $request->sizes,
                        'tax_percentage' => $request->tax_percentage,
                        'min_quantity_for_discount' => $request->min_quantity_for_discount,
                        'discounted_price' => $request->discounted_price,
                        'badge' => $request->badge,
                        'weight' => $request->weight,
                        'weight_unit' => $request->weight_unit,
                    ]);

                    $uploadedImages = [];
                    try {
                        foreach ($request->colors as $colorData) {
                            $coverImage = $this->cloudinary->upload(
                                $colorData['cover_image']->getRealPath(),
                                ['folder' => 'products']
                            );
                            $uploadedImages[] = $coverImage['secure_url'];

                            $otherImages = [];
                            if (isset($colorData['other_images'])) {
                                foreach ($colorData['other_images'] as $image) {
                                    $result = $this->cloudinary->upload(
                                        $image->getRealPath(),
                                        ['folder' => 'products']
                                    );
                                    $uploadedImages[] = $result['secure_url'];
                                    $otherImages[] = $result['secure_url'];
                                }
                            }

                            $product->colors()->create([
                                'color' => $colorData['color'],
                                'cover_image' => $coverImage['secure_url'],
                                'other_images' => $otherImages
                            ]);
                        }

                        return response()->json($product->load('colors'), 201);
                    } catch (\Exception $e) {
                        // If image upload fails, delete any uploaded images
                        foreach ($uploadedImages as $imageUrl) {
                            $this->deleteCloudinaryImage($imageUrl);
                        }
                        throw $e;
                    }
                } catch (\Exception $e) {
                    // If anything fails after product creation, delete the product
                    if (isset($product)) {
                        $product->delete();
                    }
                    throw $e;
                }
            }, 5); // 5 retries for deadlock situations
        } catch (\Exception $e) {
            \Log::error('Product creation failed: ' . $e->getMessage());
            return response()->json([
                'errors' => [
                    'general' => ['Failed to create product. Please try again.'],
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }


    public function toggleStatus($id)
    {
        try {
            $product = Product::with(['colors', 'category', 'subcategory'])->findOrFail($id);
            $product->is_published = !$product->is_published;
            $product->save();

            // Calculate total stock to determine if product is out of stock
            $sizes = is_array($product->sizes) ? $product->sizes : (json_decode($product->sizes, true) ?: []);
            $totalStock = array_reduce($sizes, function($carry, $size) {
                return $carry + (int)($size['stock'] ?? 0);
            }, 0);

            // Determine status based on stock and publication status
            $status = 'Out of Stock';
            if ($totalStock > 0) {
                $status = $product->is_published ? 'Active' : 'Inactive';
            }

            return response()->json([
                'status' => $status,
                'is_published' => $product->is_published,
                'stock' => $totalStock
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update status: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::with(['colors', 'category', 'subcategory'])->findOrFail($id);
            return response()->json([
                'id' => $product->id,
                'name' => $product->name,
                'sku_code' => $product->sku_code,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'subcategory_id' => $product->subcategory_id,
                'sizes' => is_array($product->sizes) ? $product->sizes : (json_decode($product->sizes, true) ?: []),
                'tax_percentage' => $product->tax_percentage,
                'min_quantity_for_discount' => $product->min_quantity_for_discount,
                'discounted_price' => $product->discounted_price,
                'badge' => $product->badge,
                'weight' => $product->weight,
                'weight_unit' => $product->weight_unit,
                'is_published' => $product->is_published,
                'colors' => $product->colors->map(function($color) {
                    return [
                        'id' => $color->id,
                        'color' => $color->color,
                        'cover_image' => $color->cover_image,
                        'other_images' => $color->other_images
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Product not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'sku_code' => 'required|string|unique:products,sku_code,' . $id,
                'description' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'subcategory_id' => 'required|exists:subcategories,id',
                'sizes' => 'required|json',
                'tax_percentage' => 'nullable|numeric',
                'min_quantity_for_discount' => 'nullable|integer|min:2',
                'discounted_price' => 'nullable|numeric|min:0',
                'badge' => 'nullable|string|in:New arrival,Best Seller,Hot Selling,Trending,Limited,Premium',
                'weight' => 'nullable|numeric',
                'weight_unit' => 'required|in:gram,kg',
                'colors.*.color' => 'required|string',
                'colors.*.cover_image' => 'nullable|image',
                'colors.*.other_images.*' => 'nullable|image'
            ]);

            $product->update([
                'name' => $request->name,
                'sku_code' => $request->sku_code,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'subcategory_id' => $request->subcategory_id,
                'sizes' => $request->sizes,
                'tax_percentage' => $request->tax_percentage,
                'min_quantity_for_discount' => $request->min_quantity_for_discount,
                'discounted_price' => $request->discounted_price,
                'badge' => $request->badge,
                'weight' => $request->weight,
                'weight_unit' => $request->weight_unit,
            ]);

            // Handle colors
            if ($request->has('colors')) {
                foreach ($product->colors as $color) {
                    // Only delete images that are being replaced
                    $colorData = collect($request->colors)->firstWhere('existing_cover_image', $color->cover_image);
                    if (!$colorData) {
                        $this->deleteCloudinaryImage($color->cover_image);
                        foreach ($color->other_images ?? [] as $image) {
                            if (!in_array($image, $request->input('colors.*.existing_other_images', []))) {
                                $this->deleteCloudinaryImage($image);
                            }
                        }
                    }
                }
                $product->colors()->delete();

                foreach ($request->colors as $colorData) {
                    $colorAttributes = ['color' => $colorData['color']];

                    // Handle cover image
                    if (isset($colorData['cover_image'])) {
                        $coverImage = $this->cloudinary->upload(
                            $colorData['cover_image']->getRealPath(),
                            ['folder' => 'products']
                        );
                        $colorAttributes['cover_image'] = $coverImage['secure_url'];
                    } elseif (isset($colorData['existing_cover_image'])) {
                        $colorAttributes['cover_image'] = $colorData['existing_cover_image'];
                    }

                    // Handle other images
                    $otherImages = [];
                    if (isset($colorData['other_images'])) {
                        foreach ($colorData['other_images'] as $image) {
                            $result = $this->cloudinary->upload(
                                $image->getRealPath(),
                                ['folder' => 'products']
                            );
                            $otherImages[] = $result['secure_url'];
                        }
                    }
                    if (isset($colorData['existing_other_images'])) {
                        $otherImages = array_merge($otherImages, $colorData['existing_other_images']);
                    }
                    if (!empty($otherImages)) {
                        $colorAttributes['other_images'] = $otherImages;
                    }

                    $product->colors()->create($colorAttributes);
                }
            }

            return response()->json($product->load('colors'));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::with('colors')->findOrFail($id);

            // Delete images from Cloudinary
            foreach ($product->colors as $color) {
                $this->deleteCloudinaryImage($color->cover_image);
                foreach ($color->other_images ?? [] as $image) {
                    $this->deleteCloudinaryImage($image);
                }
            }

            // Delete the product (colors will be deleted due to cascade)
            $product->delete();

            return response()->json(['message' => 'Product deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete product'], 500);
        }
    }

    private function deleteCloudinaryImage($url)
    {
        try {
            if (!$url) return;

            // Extract public_id from URL
            preg_match('/products\/[^.]+/', $url, $matches);
            if (isset($matches[0])) {
                $this->cloudinary->destroy($matches[0]);
            }
        } catch (\Exception $e) {
            // Log error but don't stop execution
            \Log::error('Failed to delete Cloudinary image: ' . $e->getMessage());
        }
    }
}
