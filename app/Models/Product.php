<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'subcategory_id',
        'name',
        'sku_code',
        'description',
        'sizes',
        'tax_percentage',
        'min_quantity_for_discount',
        'bulk_discount_amount',  // Add this
        'discounted_price',
        'badge',
        'weight',
        'weight_unit',
        'is_published'
    ];

    protected $casts = [
        'sizes' => 'array',
        'tax_percentage' => 'float',
        'min_quantity_for_discount' => 'integer',
        'bulk_discount_amount' => 'decimal:2',  // Add this
        'discounted_price' => 'float',
        'weight' => 'float',
        'is_published' => 'boolean'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function colors()
    {
        return $this->hasMany(ProductColor::class);
    }
    
    public function reviews()
    {
        return $this->hasMany(\App\Models\ProductReview::class);
    }
}
