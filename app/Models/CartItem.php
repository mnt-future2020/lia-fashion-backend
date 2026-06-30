<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'product_id',
        'product_name',
        'size',
        'color',
        'quantity',
        'unit_price',
        'original_price',
        'min_quantity_for_discount',
        'bulk_discount_amount',
        'tax_percentage',
        'tax_amount',
        'subtotal'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'bulk_discount_amount' => 'decimal:2',
        'min_quantity_for_discount' => 'integer',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    protected static function boot()
    {
        parent::boot();

        // Calculate subtotal before saving
        static::saving(function ($cartItem) {
            $cartItem->subtotal = $cartItem->unit_price * $cartItem->quantity;

            // Calculate tax if applicable
            if ($cartItem->tax_percentage > 0) {
                $cartItem->tax_amount = ($cartItem->subtotal * $cartItem->tax_percentage) / 100;
            }
        });

        // Update cart total after save/delete
        static::saved(function ($cartItem) {
            $cartItem->cart->updateTotalAmount();
        });

        static::deleted(function ($cartItem) {
            $cartItem->cart->updateTotalAmount();
        });
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
