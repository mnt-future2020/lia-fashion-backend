<?php

namespace App\Http\Controllers\PublicController;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Product::with(['colors', 'category', 'subcategory']);

            // Search by name or SKU
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku_code', 'like', "%{$search}%");
                });
            }

            // Filter by category (id or name)
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->get('category_id'));
            } elseif ($request->filled('category')) {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('name', $request->get('category'));
                });
            }

            // Filter by subcategory (id)
            if ($request->filled('subcategory_id')) {
                $query->where('subcategory_id', $request->get('subcategory_id'));
            } elseif ($request->filled('subcategory')) {
                $query->whereHas('subcategory', function ($q) use ($request) {
                    $q->where('name', $request->get('subcategory'));
                });
            }

            // Filter by collection/badge
            if ($request->filled('collection')) {
                $query->where('badge', $request->get('collection'));
            }

            // Exclude specific product id
            if ($request->filled('exclude')) {
                $query->where('id', '!=', $request->get('exclude'));
            }

            // Ordering: default latest unless random requested
            if ($request->boolean('random', false)) {
                $query->inRandomOrder();
            } else {
                $query->latest();
            }

            // Fetch
            $rawProducts = $query->get();

            // Base availability filter and transformation
            $products = $rawProducts->filter(function ($product) {
                $sizes = is_string($product->sizes) ? json_decode($product->sizes, true) : $product->sizes;
                $totalStock = collect($sizes ?: [])->sum('stock');
                return $totalStock >= 1 && $product->is_published;
            })->map(function ($product) {
                $color = $product->colors->first();
                $sizes = is_string($product->sizes) ? json_decode($product->sizes, true) : $product->sizes;

                $sizePrices = array_map(function ($size) {
                    return [
                        'size' => $size['size'] ?? '',
                        'price' => isset($size['selling_price']) ? (float)$size['selling_price'] : 0,
                        'mrp' => isset($size['mrp']) ? (float)$size['mrp'] : 0,
                        'stock' => isset($size['stock']) ? (int)$size['stock'] : 0,
                    ];
                }, $sizes ?: []);

                // Determine a base selling price (first size or 0)
                $basePrice = $sizePrices[0]['price'] ?? 0;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'sku_code' => $product->sku_code,
                    'description' => $product->description,
                    'image' => $color ? $color->cover_image : null,
                    'images' => $color ? array_merge([$color->cover_image], (array) $color->other_images) : [],
                    'category' => $product->category->name,
                    'category_id' => $product->category_id,
                    'subcategory' => $product->subcategory ? $product->subcategory->name : null,
                    'subcategory_id' => $product->subcategory_id,
                    'badge' => $product->badge,
                    'colors' => $product->colors->map(function ($color) {
                        return [
                            'name' => $color->color,
                            'main_image' => $color->cover_image,
                            'gallery' => $color->other_images,
                        ];
                    }),
                    'size_prices' => $sizePrices,
                    'tax_percentage' => $product->tax_percentage,
                    'min_quantity_for_discount' => $product->min_quantity_for_discount,
                    'discounted_price' => $product->discounted_price,
                    'weight' => $product->weight,
                    'weight_unit' => $product->weight_unit,
                    'is_published' => (bool) $product->is_published,
                    'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                    'status' => $this->getProductStatus($product, $sizes),
                    '_base_price' => $basePrice,
                ];
            });

            // Additional PHP-side filters
            if ($request->filled('sizes')) {
                $sizesFilter = array_filter(explode(',', (string) $request->get('sizes')));
                if (!empty($sizesFilter)) {
                    $products = $products->filter(function ($p) use ($sizesFilter) {
                        foreach ($p['size_prices'] as $sp) {
                            if (in_array($sp['size'], $sizesFilter, true) && $sp['stock'] > 0) {
                                return true;
                            }
                        }
                        return false;
                    });
                }
            }

            if ($request->filled('price_min') || $request->filled('price_max')) {
                $min = (float) $request->get('price_min', 0);
                $max = (float) $request->get('price_max', PHP_FLOAT_MAX);
                $products = $products->filter(function ($p) use ($min, $max) {
                    $price = (float) ($p['_base_price'] ?? 0);
                    return $price >= $min && $price <= $max;
                });
            }

            // Sorting
            $sort = $request->get('sort');
            if ($sort === 'price-low-high') {
                $products = $products->sortBy(function ($p) {
                    return (float) ($p['_base_price'] ?? 0);
                })->values();
            } elseif ($sort === 'price-high-low') {
                $products = $products->sortByDesc(function ($p) {
                    return (float) ($p['_base_price'] ?? 0);
                })->values();
            }

            // Pagination (server-side style over the transformed collection)
            $perPage = (int) $request->get('per_page', 25);
            if ($perPage <= 0) { $perPage = 25; }
            $page = (int) $request->get('page', 1);
            if ($page <= 0) { $page = 1; }

            $total = $products->count();
            $lastPage = (int) ceil($total / $perPage);
            $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to = min($page * $perPage, $total);
            $pageData = $products->forPage($page, $perPage)->map(function ($p) {
                // Remove internal fields
                unset($p['_base_price']);
                return $p;
            })->values();

            return response()->json([
                'data' => $pageData,
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::with(['colors', 'category', 'subcategory'])
                ->findOrFail($id);

            $sizes = is_string($product->sizes) ? json_decode($product->sizes, true) : $product->sizes;

            return response()->json([
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku_code' => $product->sku_code,
                'description' => $product->description,
                'image' => $product->colors->first()?->cover_image,
                'images' => $product->colors->flatMap(function($color) {
                    return array_merge([$color->cover_image], (array)$color->other_images);
                })->unique()->values(),
                'category' => [
                    'id' => $product->category->id,
                    'name' => $product->category->name
                ],
                'subcategory' => $product->subcategory ? [
                    'id' => $product->subcategory->id,
                    'name' => $product->subcategory->name
                ] : null,
                'badge' => $product->badge,
                'colors' => $product->colors->map(function($color) {
                    return [
                        'name' => $color->color,
                        'main_image' => $color->cover_image,
                        'gallery' => $color->other_images
                    ];
                }),
                'size_prices' => array_map(function($size) {
                    return [
                        'size' => $size['size'] ?? '',
                        'price' => isset($size['selling_price']) ? (float)$size['selling_price'] : 0,
                        'mrp' => isset($size['mrp']) ? (float)$size['mrp'] : 0,
                        'stock' => isset($size['stock']) ? (int)$size['stock'] : 0
                    ];
                }, $sizes ?: []),
                'status' => $this->calculateStockStatus($sizes ?: []),
                'weight' => [
                    'value' => $product->weight,
                    'unit' => $product->weight_unit
                ],
                'created_at' => $product->created_at->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Product not found'], 404);
        }
    }

    public function getReviews($id)
    {
        $product = Product::findOrFail($id);
        $reviews = $product->reviews()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'average' => $reviews->avg('rating') ?? 0,
            'total' => $reviews->count(),
            'distribution' => [
                5 => $reviews->where('rating', 5)->count(),
                4 => $reviews->where('rating', 4)->count(),
                3 => $reviews->where('rating', 3)->count(),
                2 => $reviews->where('rating', 2)->count(),
                1 => $reviews->where('rating', 1)->count(),
            ]
        ];

        return response()->json([
            'reviews' => $reviews,
            'stats' => $stats
        ]);
    }

    private function getProductStatus($product, $sizes)
    {
        $totalStock = collect($sizes ?: [])->sum('stock');
        if (!$product->is_published) return 'inactive';
        if ($totalStock <= 0) return 'out_of_stock';
        if ($totalStock <= 20) return 'low_stock';
        return 'in_stock';
    }

    private function calculateStockStatus($sizes)
    {
        $totalStock = collect($sizes)->sum('stock');
        if ($totalStock <= 0) return 'out_of_stock';
        if ($totalStock <= 20) return 'low_stock';
        return 'in_stock';
    }
}
