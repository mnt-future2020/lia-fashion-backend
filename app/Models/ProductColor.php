<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductColor extends Model
{
    protected $fillable = [
        'product_id',
        'color',
        'cover_image',
        'other_images'
    ];

    protected $casts = [
        'other_images' => 'array'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
